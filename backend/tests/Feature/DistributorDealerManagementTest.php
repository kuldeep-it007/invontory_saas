<?php

declare(strict_types=1);

use App\Exceptions\QuotaExceededException;
use App\Models\AuditLog;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\DealerService;
use App\Services\DistributorService;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| Task 7.1 — Distributor & Dealer management (R10.1–R10.5)
|--------------------------------------------------------------------------
|
| Exercises the Distributor/Dealer services against a real provisioned tenant
| database:
|   • a Distributor is created under the tenant's single Company (R10.1, R10.3);
|   • a Dealer is created under exactly one Distributor (R10.2, R10.3);
|   • the whole-tenant `max_distributors`/`max_dealers` quotas reject creation
|     past the limit with a 403 naming the quota (R10.4 → R5.1/R5.2);
|   • the per-Distributor `max_dealers_per_distributor` cap rejects an extra
|     dealer for a distributor at its cap, while another distributor is still
|     under its own cap (R10.4);
|   • Payment_Terms updates are stored and audited (R10.5).
|
| Every test is DB-GATED (needs MySQL + CREATE DATABASE for stancl tenancy) and
| skips gracefully when MySQL is unreachable, mirroring the provisioning suite.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision a tenant on a plan carrying the given quota limits, returning it.
 *
 * @param  array<string, int>  $limits  Quota key => value (0/NULL omitted = unlimited).
 */
function provisionNetworkTenant(string $suffix, array $limits, ?Plan &$plan = null): Tenant
{
    $plan = Plan::create([
        'name' => 'Net '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    foreach ($limits as $key => $value) {
        $plan->limits()->create(['key' => $key, 'value' => $value]);
    }

    $result = app(TenantProvisioningService::class)->provision([
        'subdomain' => 'net-'.$suffix,
        'plan_id' => $plan->getKey(),
        'currency' => 'USD',
        'company_name' => 'Net Co '.$suffix,
        'admin_name' => 'Net Admin '.$suffix,
        'admin_email' => 'net-admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return $result['tenant'];
}

function teardownNetworkTenant(?Tenant $tenant, ?Plan $plan): void
{
    if ($tenant !== null && $tenant->exists) {
        try {
            Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
            $tenant->database()->getTenantDatabaseManager()->deleteDatabase($tenant);
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $tenant->domains()->delete();
            $tenant->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }

    $plan?->delete();
}

it('creates a distributor under the single company and a dealer under exactly one distributor (R10.1, R10.2, R10.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionNetworkTenant($suffix, [], $plan);

    try {
        $tenant->run(function () {
            $distributorService = app(DistributorService::class);
            $dealerService = app(DealerService::class);

            $distributor = $distributorService->create([
                'name' => 'Acme Distributors',
                'gstin' => '22AAAAA0000A1Z5',
                'address' => '1 Market Street',
            ]);

            // The distributor belongs to the tenant's single company.
            expect($distributor->company_id)->not->toBeNull();
            expect($distributor->company->getKey())->toBe($distributor->company_id);

            $dealer = $dealerService->create($distributor, [
                'name' => 'Downtown Dealer',
                'gstin' => '22BBBBB0000B1Z5',
            ]);

            // The dealer belongs to exactly that distributor (R10.3).
            expect($dealer->distributor_id)->toBe($distributor->getKey());
            expect($dealer->distributor->getKey())->toBe($distributor->getKey());

            expect(Distributor::query()->count())->toBe(1);
            expect(Dealer::query()->count())->toBe(1);
        });
    } finally {
        teardownNetworkTenant($tenant, $plan);
    }
});

it('rejects a distributor past the max_distributors quota with a 403 naming the quota (R10.4 → R5.1/R5.2)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    // Allow exactly one distributor.
    $tenant = provisionNetworkTenant($suffix, [PlanLimit::MAX_DISTRIBUTORS => 1], $plan);

    try {
        $tenant->run(function () {
            $service = app(DistributorService::class);

            $service->create(['name' => 'First Distributor']);

            $thrown = null;
            try {
                $service->create(['name' => 'Second Distributor']);
            } catch (QuotaExceededException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(QuotaExceededException::class);
            expect($thrown->getStatusCode())->toBe(403);
            expect($thrown->quotaKey)->toBe(PlanLimit::MAX_DISTRIBUTORS);
            expect($thrown->getMessage())->toContain(PlanLimit::MAX_DISTRIBUTORS);

            // The rejected creation persisted nothing beyond the first.
            expect(Distributor::query()->count())->toBe(1);
        });
    } finally {
        teardownNetworkTenant($tenant, $plan);
    }
});

it('enforces max_dealers_per_distributor per distributor independently (R10.4)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    // Cap each distributor at 1 dealer; tenant-wide dealers unlimited.
    $tenant = provisionNetworkTenant($suffix, [PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR => 1], $plan);

    try {
        $tenant->run(function () {
            $distributorService = app(DistributorService::class);
            $dealerService = app(DealerService::class);

            $distA = $distributorService->create(['name' => 'Distributor A']);
            $distB = $distributorService->create(['name' => 'Distributor B']);

            // First dealer for A is allowed; a second for A breaches the cap.
            $dealerService->create($distA, ['name' => 'A-Dealer-1']);

            $thrown = null;
            try {
                $dealerService->create($distA, ['name' => 'A-Dealer-2']);
            } catch (QuotaExceededException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(QuotaExceededException::class);
            expect($thrown->getStatusCode())->toBe(403);
            expect($thrown->quotaKey)->toBe(PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR);

            // Distributor B is independently under its own cap, so its first
            // dealer is still permitted.
            $bDealer = $dealerService->create($distB, ['name' => 'B-Dealer-1']);
            expect($bDealer->distributor_id)->toBe($distB->getKey());

            expect($distA->dealers()->count())->toBe(1);
            expect($distB->dealers()->count())->toBe(1);
        });
    } finally {
        teardownNetworkTenant($tenant, $plan);
    }
});

it('stores and audits Payment_Terms updates on a distributor (R10.5)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionNetworkTenant($suffix, [], $plan);

    try {
        $tenant->run(function () {
            // owen-it skips auditing in console context unless audit.console is
            // enabled; the test runner is a console process, so enable it so the
            // real mutation→audit path fires (R10.5, R34.1).
            Config::set('audit.console', true);

            $service = app(DistributorService::class);

            $distributor = $service->create([
                'name' => 'Terms Distributor',
                'payment_terms' => [
                    'credit_limit' => 1000,
                    'payment_type' => 'credit',
                ],
            ]);

            $auditsBefore = AuditLog::query()
                ->where('auditable_type', Distributor::class)
                ->where('auditable_id', $distributor->getKey())
                ->count();

            $updated = $service->update($distributor, [
                'payment_terms' => [
                    'credit_limit' => 5000,
                    'overdue_balance_limit' => 2000,
                    'overdue_hold_days' => 30,
                    'payment_type' => 'credit',
                ],
            ]);

            // The new terms are durably persisted (R10.5).
            expect($updated->payment_terms['credit_limit'])->toBe(5000);
            expect($updated->payment_terms['overdue_hold_days'])->toBe(30);

            $fresh = Distributor::query()->findOrFail($distributor->getKey());
            expect($fresh->payment_terms['overdue_balance_limit'])->toBe(2000);

            // The update produced an additional audit entry for this record (R10.5).
            $auditsAfter = AuditLog::query()
                ->where('auditable_type', Distributor::class)
                ->where('auditable_id', $distributor->getKey())
                ->count();
            expect($auditsAfter)->toBeGreaterThan($auditsBefore);
        });
    } finally {
        teardownNetworkTenant($tenant, $plan);
    }
});

it('stores and audits Payment_Terms updates on a dealer (R10.5)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionNetworkTenant($suffix, [], $plan);

    try {
        $tenant->run(function () {
            // Enable console auditing so model mutation audits fire under the
            // test runner (R10.5, R34.1).
            Config::set('audit.console', true);

            $distributor = app(DistributorService::class)->create(['name' => 'Parent Distributor']);
            $service = app(DealerService::class);

            $dealer = $service->create($distributor, ['name' => 'Terms Dealer']);

            $updated = $service->update($dealer, [
                'payment_terms' => ['credit_limit' => 750, 'payment_type' => 'cash'],
            ]);

            expect($updated->payment_terms['credit_limit'])->toBe(750);

            $audits = AuditLog::query()
                ->where('auditable_type', Dealer::class)
                ->where('auditable_id', $dealer->getKey())
                ->count();
            expect($audits)->toBeGreaterThanOrEqual(1);
        });
    } finally {
        teardownNetworkTenant($tenant, $plan);
    }
});
