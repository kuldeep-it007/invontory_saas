<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/*
|--------------------------------------------------------------------------
| Smoke (DB-gated): TenantProvisioningService happy path + duplicate 409
|--------------------------------------------------------------------------
|
| Task 3.1 smoke coverage (full unit tests live in task 3.2):
|  - Provisioning creates the central Tenant + domain + Subscription (R2.2),
|    creates/migrates a separate tenant database, and seeds the single Company
|    and initial Company_Admin inside it (R2.1).
|  - A duplicate subdomain is rejected with a 409-mapped ConflictHttpException
|    before any tenant database is created (R2.3).
|
| DB-GATED: requires a reachable MySQL server with CREATE DATABASE privileges.
| When MySQL is unreachable each test is reported SKIPPED, never failed.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Build a minimal provisioning input for an isolated test subdomain.
 *
 * @return array<string, mixed>
 */
function provisioningInput(string $suffix, int $planId): array
{
    return [
        'subdomain' => 'prov-'.$suffix,
        'plan_id' => $planId,
        'currency' => 'USD',
        'company_name' => 'Acme Co '.$suffix,
        'admin_name' => 'Admin '.$suffix,
        'admin_email' => 'admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ];
}

/**
 * Delete a provisioned tenant and its central rows (best-effort cleanup).
 */
function cleanupProvisionedTenant(?Tenant $tenant): void
{
    if ($tenant === null || ! $tenant->exists) {
        return;
    }

    try {
        Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
        $tenant->database()->getTenantDatabaseManager()->deleteDatabase($tenant);
    } catch (\Throwable $e) {
        // best-effort
    }
    try {
        $tenant->domains()->delete();
        $tenant->delete();
    } catch (\Throwable $e) {
    }
}

it('provisions a tenant end-to-end: central rows + tenant company/admin (R2.1, R2.2)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $service = app(TenantProvisioningService::class);
        $result = $service->provision(provisioningInput($suffix, $plan->getKey()));
        $tenant = $result['tenant'];

        // Central: tenant, domain, and subscription recorded (R2.2).
        expect($tenant->subdomain)->toBe('prov-'.$suffix);
        expect($tenant->domains()->count())->toBe(1);

        $subscription = $result['subscription'];
        expect($subscription->plan_id)->toBe($plan->getKey());
        expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE);

        // Tenant DB: exactly one Company and a Company_Admin user (R2.1, R1.5).
        $tenant->run(function () use ($result) {
            expect(Company::query()->count())->toBe(1);
            expect(Company::query()->whereKey($result['company_id'])->exists())->toBeTrue();

            $admin = User::query()->findOrFail($result['admin_id']);
            expect($admin->hasRole(Roles::COMPANY_ADMIN))->toBeTrue();
            // Password is stored hashed, never plaintext (R7.3).
            expect($admin->password)->not->toBe('password123');
        });
    } finally {
        cleanupProvisionedTenant($tenant);
        $plan->delete();
    }
});

it('rejects a duplicate subdomain with a 409 (R2.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $service = app(TenantProvisioningService::class);
        $result = $service->provision(provisioningInput($suffix, $plan->getKey()));
        $tenant = $result['tenant'];

        // Second provision with the SAME subdomain must raise a 409 conflict
        // and create no second tenant.
        $before = Tenant::query()->count();

        expect(fn () => $service->provision(provisioningInput($suffix, $plan->getKey())))
            ->toThrow(ConflictHttpException::class);

        expect(Tenant::query()->count())->toBe($before);
    } finally {
        cleanupProvisionedTenant($tenant);
        $plan->delete();
    }
});
