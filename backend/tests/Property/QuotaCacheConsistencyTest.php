<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuotaService;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 16: Quota cache consistency
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 16: Quota cache consistency
|
| Validates: Requirements 5.6
|
| Design reference: "Quota Caching Model" / QuotaService.
|   - R5.6  THE Platform SHALL cache per-Tenant resource counts and limits and
|           invalidate the cache when a counted resource is created or deleted.
|
| The invariant. QuotaService::count() is served from a per-tenant cache. The
| cache is a performance layer, never a source of truth: after ANY sequence of
| create/delete operations on a counted resource, once the cache has been
| invalidated (as production code does on every create/delete), the CACHED
| count MUST equal the ACTUAL row count in the active tenant database. The
| cache may be stale between a mutation and its invalidation, but invalidation
| always reconciles it — so the observable count a caller sees is correct.
|
| Strategy. We provision ONE real tenant database once per proof, then let
| black-box generate 100+ randomized sequences of +1 (create user) / -1 (delete
| user) operations. The seeded Company_Admin establishes a floor of 1 user, so
| the model clamps deletes at that floor (we never delete the seeded admin).
| For each generated sequence we:
|
|   1. Warm the cache (QuotaService::count reflects the starting DB count).
|   2. Apply each op to the tenant DB, then invalidate the quota (mirroring the
|      create/delete + invalidate contract of R5.6).
|   3. Assert after each invalidation the cached count == actual DB count.
|   4. As an explicit staleness check: mutate WITHOUT invalidating and show the
|      cache can lag, then invalidate and show it reconciles to the DB count.
|
| We use `max_users` as the counted resource because the `users` table is the
| one counted table that physically exists in this milestone (distributors /
| dealers / products / stock_points arrive in M3); QuotaService counts a
| resource only when its table exists, so `max_users` is the correct,
| milestone-safe probe for the caching invariant.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges. When MySQL is unreachable
| (local WAMP down, CI without a MySQL service) the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance.
*/

/**
 * Provision an isolated tenant on a finite-limit plan and migrate its tenant
 * database. The tenant DB is seeded with exactly one Company_Admin user, so the
 * baseline `max_users` count is 1. Registered for teardown via the registry.
 *
 * @param array<int, Tenant> $registry
 */
function makeQuotaCachePlan(string $name, array $limits): Plan
{
    $plan = Plan::create([
        'name' => $name,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    foreach ($limits as $key => $value) {
        $plan->limits()->create(['key' => $key, 'value' => $value]);
    }

    return $plan->fresh('limits');
}

function provisionQuotaCacheTenant(array &$registry, Plan $plan, string $suffix): Tenant
{
    $service = app(TenantProvisioningService::class);

    $result = $service->provision([
        'subdomain' => 'qcache-'.$suffix,
        'plan_id' => $plan->getKey(),
        'currency' => 'USD',
        'company_name' => 'QCache Co '.$suffix,
        'admin_name' => 'Admin '.$suffix,
        'admin_email' => 'admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    /** @var Tenant $tenant */
    $tenant = $result['tenant'];
    $registry[] = $tenant;

    return $tenant;
}

/**
 * Best-effort teardown: drop the provisioned tenant database, its central
 * subscription, domain, and tenant registry row.
 *
 * @param array<int, Tenant> $tenants
 */
function teardownQuotaCacheTenants(array $tenants): void
{
    tenancy()->end();

    foreach ($tenants as $t) {
        try {
            Subscription::query()->where('tenant_id', $t->getTenantKey())->delete();
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }
}

it('reconciles the cached quota count to the actual DB count after any create/delete sequence and invalidation (Property 16, R5.6)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $suffix = strtolower(bin2hex(random_bytes(5)));
        $plan = makeQuotaCachePlan('QCache '.$suffix, [PlanLimit::MAX_USERS => 1000]);
        $tenant = provisionQuotaCacheTenant($tenants, $plan, $suffix);

        // ------------------------------------------------------------------
        // Generated invariant: for any sequence of create(+1)/delete(-1) ops,
        // the cached count equals the live DB count after each invalidation.
        // 100+ randomized iterations (BlackBox default scenarii-per-proof).
        // Each element is a boolean: true = create a user, false = delete one.
        // ------------------------------------------------------------------
        static::forAll(
            Set::sequence(Set::of(true, false))->between(1, 12),
        )->then(function (array $ops) use ($tenant, $plan) {
            $tenant->run(function () use ($ops, $plan) {
                /** @var QuotaService $quota */
                $quota = app(QuotaService::class);

                $actualDbCount = fn (): int => (int) DB::connection('tenant')->table('users')->count();

                // (1) Warm the cache; it must agree with the DB from the start.
                $quota->invalidate(PlanLimit::MAX_USERS);
                expect($quota->count(PlanLimit::MAX_USERS))->toBe($actualDbCount());

                foreach ($ops as $create) {
                    if ($create) {
                        // Create a counted resource (a tenant user).
                        $u = bin2hex(random_bytes(6));
                        User::create([
                            'name' => 'User '.$u,
                            'email' => 'user-'.$u.'@example.test',
                            'password' => 'password123',
                        ]);
                    } else {
                        // Delete a counted resource, but never below the floor
                        // of 1 (the seeded Company_Admin is left in place).
                        $deletable = User::query()->orderByDesc('id')->first();

                        if ($deletable !== null && $actualDbCount() > 1) {
                            $deletable->delete();
                        }
                    }

                    // (2) Mirror the create/delete + invalidate contract (R5.6).
                    $quota->invalidate(PlanLimit::MAX_USERS);

                    // (3) After invalidation the cached count MUST equal the DB.
                    expect($quota->count(PlanLimit::MAX_USERS))->toBe($actualDbCount());
                }

                // (4) Staleness reconciliation: mutate WITHOUT invalidating so
                // the cache may lag the DB, then invalidate and prove the cached
                // count reconciles exactly to the live DB count (R5.6).
                $before = $actualDbCount();

                $s = bin2hex(random_bytes(6));
                User::create([
                    'name' => 'Stale '.$s,
                    'email' => 'stale-'.$s.'@example.test',
                    'password' => 'password123',
                ]);

                // The DB truly grew by one...
                expect($actualDbCount())->toBe($before + 1);

                // ...and once we invalidate, the cache reflects the new truth.
                $quota->invalidate(PlanLimit::MAX_USERS);
                expect($quota->count(PlanLimit::MAX_USERS))->toBe($actualDbCount());
            });
        });
    } finally {
        teardownQuotaCacheTenants($tenants);
    }
});
