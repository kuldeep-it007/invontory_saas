<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\TenancyServiceProvider;
use App\Support\StarterPlan;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Orchestrates end-to-end tenant provisioning (R2).
 *
 * Responsibilities, in order:
 *
 *   1. Reject a duplicate subdomain (or domain) with HTTP 409 BEFORE any
 *      database is created (R2.3).
 *   2. In the CENTRAL database, create the {@see Tenant} registry row, its
 *      {@see Domain} resolution row, and the {@see Subscription}
 *      with the selected {@see Plan} (R2.2). The Tenant's business toggles
 *      (currency, backorders, dealer-stock) are set here too.
 *   3. Creating the Tenant fires stancl/tenancy's `TenantCreated` job
 *      pipeline (CreateDatabase → MigrateDatabase → SeedDatabase — see
 *      {@see TenancyServiceProvider}), which creates and
 *      migrates the dedicated per-tenant database and seeds the baseline RBAC
 *      roles via {@see TenantDatabaseSeeder} (R2.1).
 *   4. INSIDE the new tenant database, seed the single Company and the initial
 *      Company_Admin user, and assign that user the Company_Admin role (R2.1,
 *      R1.5/R1.6 singleton).
 *   5. Record the provisioning action on the CENTRAL side (R2.4).
 *
 * Provisioning is best-effort transactional across databases: the central
 * rows are written in a central-connection transaction, and if the per-tenant
 * seeding fails the already-created tenant database and central rows are torn
 * down so a failed provision leaves no partial tenant behind.
 *
 * ## Central-side audit approach (R2.4)
 *
 * The platform's structured audit trail (`audit_logs`, owen-it + AuditRecorder)
 * is a TENANT-side table — it only exists inside each tenant's own database and
 * is reachable only once tenancy is initialized. Provisioning, by design, runs
 * on the `platform.admin` route group WITHOUT tenancy initialization and
 * creates central rows, so there is no tenant `audit_logs` table to target for
 * the central action and no central audit table exists in this milestone.
 *
 * The simplest coherent central-side audit is therefore Laravel's application
 * logger: a single structured `tenant.provisioned` log entry recording the
 * actor, tenant id, subdomain, plan, and timestamp. This keeps a durable,
 * centralized record of every provisioning action without inventing a new
 * central schema here. (A dedicated central audit table can replace this log
 * sink later without changing the service's contract.)
 */
class TenantProvisioningService
{
    /**
     * Starter-tier default quotas applied when a tenant is provisioned on the
     * Starter plan without overriding quotas (R4.7). This mirrors the single
     * source of truth in {@see StarterPlan::LIMITS}; the quota rows themselves
     * live on the Plan in the central database and are created via
     * {@see StarterPlan::provision()}.
     *
     * @var array<string, int>
     */
    public const STARTER_DEFAULTS = StarterPlan::LIMITS;

    /**
     * Idempotently ensure the Starter plan (with its quota limits and feature
     * flags) exists in the central database, returning it (R4.7). Provisioning
     * uses this so a tenant can be started on the Starter tier without the
     * caller having to pre-create the plan.
     */
    public function ensureStarterPlan(): Plan
    {
        return StarterPlan::provision();
    }

    /**
     * Provision a new tenant end-to-end.
     *
     * @param  array{
     *     subdomain: string,
     *     domain?: string|null,
     *     plan_id: int,
     *     currency?: string|null,
     *     backorders_enabled?: bool,
     *     dealer_stock_enabled?: bool,
     *     company_name: string,
     *     company_gstin?: string|null,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string,
     *     subscription_status?: string,
     * }  $input  Validated provisioning input.
     * @param  User|null  $actor  The Platform_Admin performing the action, for the central audit record.
     * @return array{tenant: Tenant, subscription: Subscription, company_id: int, admin_id: int}
     *
     * @throws ConflictHttpException When the subdomain or domain already exists (R2.3).
     */
    public function provision(array $input, ?User $actor = null): array
    {
        // Pin the active auth guard to `web` for the duration of provisioning.
        // When this runs on the authenticated `platform.admin` route, Sanctum
        // has set the active guard to `sanctum`; spatie then creates/looks up
        // tenant roles and permissions under `sanctum`, which collides with the
        // `web`-guarded RBAC the app seeds and assigns (GuardDoesNotMatch).
        // Resetting the active guard here keeps all role/permission work during
        // provisioning on `web`, matching RoleSeeder and the User model guard.
        auth()->shouldUse('web');

        $subdomain = $input['subdomain'];
        $domain = $input['domain'] ?? ($subdomain.'.'.config('tenancy.central_domains.0', 'localhost'));

        // (1) Duplicate-subdomain guard BEFORE creating any database (R2.3).
        $this->assertSubdomainAvailable($subdomain, $domain);

        $plan = Plan::query()->findOrFail($input['plan_id']);

        $tenant = null;

        try {
            // (2) Central registry rows in a single central transaction (R2.2).
            //     Creating the Tenant triggers the stancl TenantCreated job
            //     pipeline (CreateDatabase → MigrateDatabase → SeedDatabase),
            //     building and seeding the per-tenant database (R2.1).
            $tenant = DB::connection('central')->transaction(function () use ($input, $subdomain, $domain, $plan): Tenant {
                /** @var Tenant $tenant */
                $tenant = Tenant::create([
                    'subdomain' => $subdomain,
                    'currency' => $input['currency'] ?? 'USD',
                    'backorders_enabled' => $input['backorders_enabled'] ?? false,
                    'dealer_stock_enabled' => $input['dealer_stock_enabled'] ?? false,
                ]);

                $tenant->domains()->create(['domain' => $domain]);

                Subscription::create([
                    'tenant_id' => $tenant->getTenantKey(),
                    'plan_id' => $plan->getKey(),
                    'status' => $input['subscription_status'] ?? Subscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                ]);

                return $tenant;
            });

            // (3)/(4) Seed the tenant's single Company and initial Company_Admin
            //         INSIDE the tenant database (R2.1). The RBAC roles were
            //         already seeded by the TenantCreated pipeline, so the
            //         Company_Admin role exists to assign here.
            $seeded = $this->seedTenantCompanyAndAdmin($tenant, $input);
        } catch (Throwable $e) {
            // Tear down a partially provisioned tenant so a failure leaves no
            // orphaned database or central rows behind.
            $this->rollbackTenant($tenant);

            throw $e;
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->latest('id')
            ->firstOrFail();

        // (5) Central-side provisioning audit (R2.4) — see class docblock.
        $this->auditProvisioning($tenant, $plan, $subdomain, $domain, $actor);

        return [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'company_id' => $seeded['company_id'],
            'admin_id' => $seeded['admin_id'],
        ];
    }

    /**
     * Reject provisioning with HTTP 409 if the subdomain or resolution domain
     * is already taken in the central database (R2.3).
     *
     * @throws ConflictHttpException
     */
    private function assertSubdomainAvailable(string $subdomain, string $domain): void
    {
        $subdomainTaken = Tenant::query()->where('subdomain', $subdomain)->exists();
        $domainTaken = DB::connection('central')->table('domains')->where('domain', $domain)->exists();

        if ($subdomainTaken || $domainTaken) {
            throw new ConflictHttpException(
                "The subdomain [{$subdomain}] is already in use.",
            );
        }
    }

    /**
     * Seed the single Company and the initial Company_Admin user inside the
     * freshly created tenant database (R2.1). Runs in tenant context so every
     * write lands in the tenant's own database.
     *
     * @param  array<string, mixed>  $input
     * @return array{company_id: int, admin_id: int}
     */
    private function seedTenantCompanyAndAdmin(Tenant $tenant, array $input): array
    {
        return $tenant->run(function () use ($input): array {
            // Flush spatie's permission cache so the roles seeded by the
            // TenantCreated pipeline are visible on this (tenant) connection.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return DB::connection('tenant')->transaction(function () use ($input): array {
                // Enforce the one-company singleton rule in the application
                // layer (R1.5/R1.6): never create a second company.
                $company = Company::query()->first() ?? Company::create([
                    'name' => $input['company_name'],
                    'gstin' => $input['company_gstin'] ?? null,
                ]);

                $admin = User::create([
                    'name' => $input['admin_name'],
                    'email' => $input['admin_email'],
                    'password' => $input['admin_password'], // hashed by the `hashed` cast (R7.3)
                ]);

                $role = Role::findByName(Roles::COMPANY_ADMIN, config('auth.defaults.guard', 'web'));
                $admin->assignRole($role);

                return [
                    'company_id' => $company->getKey(),
                    'admin_id' => $admin->getKey(),
                ];
            });
        });
    }

    /**
     * Record the provisioning action centrally (R2.4). See the class docblock
     * for why the application logger is the chosen central-side audit sink.
     */
    private function auditProvisioning(
        Tenant $tenant,
        Plan $plan,
        string $subdomain,
        string $domain,
        ?User $actor,
    ): void {
        try {
            Log::channel(config('logging.default'))->info('tenant.provisioned', [
                'event' => 'tenant.provisioned',
                'tenant_id' => $tenant->getTenantKey(),
                'subdomain' => $subdomain,
                'domain' => $domain,
                'plan_id' => $plan->getKey(),
                'plan_name' => $plan->name,
                'actor_id' => $actor?->getKey(),
                'actor_email' => $actor?->email,
                'provisioned_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            // Best-effort: a central-audit failure must never undo a tenant
            // that is otherwise fully provisioned (mirrors R3.5's audit stance).
        }
    }

    /**
     * Best-effort teardown of a partially provisioned tenant: delete the
     * per-tenant database (via the stancl DeleteDatabase pipeline on
     * TenantDeleted) and the central tenant/domain/subscription rows.
     */
    private function rollbackTenant(?Tenant $tenant): void
    {
        if ($tenant === null || ! $tenant->exists) {
            return;
        }

        try {
            Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
            $tenant->domains()->delete();
            // Deleting the tenant fires the TenantDeleted pipeline which drops
            // the per-tenant database.
            $tenant->delete();
        } catch (Throwable $e) {
            // Swallow cleanup failures; the original error is what we rethrow.
        }
    }
}
