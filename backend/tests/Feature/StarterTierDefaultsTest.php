<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use App\Support\StarterPlan;

/*
|--------------------------------------------------------------------------
| Task 5.2 — Unit test for starter-tier defaults (R4.7)
|--------------------------------------------------------------------------
|
| R4.7: WHEN a new Tenant is provisioned on the Starter tier without
| overriding quotas, THE Platform SHALL set:
|   max_distributors = 5
|   max_dealers      = 50
|   max_products     = 200
|   max_stock_points = 3
|   max_users        = 10
|
| These defaults are owned by App\Support\StarterPlan (the single source of
| truth referenced by both the PlanSeeder and tenant provisioning). This suite
| pins the contract from two angles:
|
|   • A no-DB assertion that the StarterPlan::LIMITS constant carries exactly
|     the five R4.7 values and nothing more — this always runs, so the defaults
|     are locked even where MySQL is unavailable.
|   • A DB-gated end-to-end assertion that provisioning a tenant on the Starter
|     tier WITHOUT quota overrides yields an active plan whose resolved limits
|     equal the R4.7 values, with every other quota key left UNLIMITED (R4.4).
|
| The DB-gated cases require MySQL + CREATE DATABASE for stancl tenancy and skip
| gracefully when MySQL is unreachable, mirroring the provisioning/white-label
| suites.
*/

/**
 * The exact starter-tier quota defaults mandated by R4.7.
 *
 * @var array<string, int>
 */
const STARTER_R47_DEFAULTS = [
    PlanLimit::MAX_DISTRIBUTORS => 5,
    PlanLimit::MAX_DEALERS => 50,
    PlanLimit::MAX_PRODUCTS => 200,
    PlanLimit::MAX_STOCK_POINTS => 3,
    PlanLimit::MAX_USERS => 10,
];

/**
 * Best-effort teardown: drop the tenant database and remove central rows.
 */
function teardownStarterTenant(?Tenant $tenant): void
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
        // best-effort
    }
}

it('defines exactly the five R4.7 starter quota defaults as the single source of truth', function () {
    // The constant is the single source of truth for provisioning + seeding, so
    // locking it guarantees the R4.7 values regardless of DB availability.
    expect(StarterPlan::LIMITS)->toBe(STARTER_R47_DEFAULTS);

    // Spelled out individually so a regression names the exact offending quota.
    expect(StarterPlan::LIMITS[PlanLimit::MAX_DISTRIBUTORS])->toBe(5);
    expect(StarterPlan::LIMITS[PlanLimit::MAX_DEALERS])->toBe(50);
    expect(StarterPlan::LIMITS[PlanLimit::MAX_PRODUCTS])->toBe(200);
    expect(StarterPlan::LIMITS[PlanLimit::MAX_STOCK_POINTS])->toBe(3);
    expect(StarterPlan::LIMITS[PlanLimit::MAX_USERS])->toBe(10);

    // Keys outside the five are intentionally omitted (unlimited under R4.4).
    expect(StarterPlan::LIMITS)->not->toHaveKey(PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR);
    expect(StarterPlan::LIMITS)->not->toHaveKey(PlanLimit::MAX_ORDERS_PER_MONTH);
    expect(StarterPlan::LIMITS)->not->toHaveKey(PlanLimit::MAX_STORAGE_MB);
});

it('provisions a Starter tenant without overrides carrying exactly the R4.7 quota defaults (R4.7)', function () {
    skip_without_mysql('central');

    $suffix = strtolower(bin2hex(random_bytes(4)));
    $service = app(TenantProvisioningService::class);

    // Ensure the Starter plan (with its R4.7 limits) exists centrally, exactly
    // as provisioning would for a new Starter tenant.
    $plan = $service->ensureStarterPlan();

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        // Provision on the Starter plan WITHOUT passing any quota overrides.
        $result = $service->provision([
            'subdomain' => 'starter-'.$suffix,
            'plan_id' => $plan->getKey(),
            'currency' => 'USD',
            'company_name' => 'Starter Co '.$suffix,
            'admin_name' => 'Starter Admin '.$suffix,
            'admin_email' => 'starter-admin-'.$suffix.'@example.test',
            'admin_password' => 'password123',
        ]);
        $tenant = $result['tenant'];

        // Resolve the plan actually bound to the new tenant's subscription and
        // assert each R4.7 default resolves to its mandated value.
        /** @var Plan $activePlan */
        $activePlan = $result['subscription']->plan()->with('limits')->firstOrFail();

        expect($activePlan->limitValue(PlanLimit::MAX_DISTRIBUTORS))->toBe(5);
        expect($activePlan->limitValue(PlanLimit::MAX_DEALERS))->toBe(50);
        expect($activePlan->limitValue(PlanLimit::MAX_PRODUCTS))->toBe(200);
        expect($activePlan->limitValue(PlanLimit::MAX_STOCK_POINTS))->toBe(3);
        expect($activePlan->limitValue(PlanLimit::MAX_USERS))->toBe(10);

        // Exactly the five R4.7 limit rows are present — no stray quotas.
        $limits = $activePlan->limits
            ->pluck('value', 'key')
            ->map(fn ($v) => (int) $v)
            ->all();
        // The contract is the key→value mapping, not the row insertion order,
        // so compare canonically (same keys and values, order-insensitive).
        expect($limits)->toEqualCanonicalizing(STARTER_R47_DEFAULTS);
        expect($limits)->toHaveCount(count(STARTER_R47_DEFAULTS));

        // Every other supported quota key is left UNLIMITED (R4.4): no row, so
        // isUnlimited() is true.
        expect($activePlan->isUnlimited(PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR))->toBeTrue();
        expect($activePlan->isUnlimited(PlanLimit::MAX_ORDERS_PER_MONTH))->toBeTrue();
        expect($activePlan->isUnlimited(PlanLimit::MAX_STORAGE_MB))->toBeTrue();

        // The resolved R4.7 defaults are never treated as unlimited.
        expect($activePlan->isUnlimited(PlanLimit::MAX_DISTRIBUTORS))->toBeFalse();
        expect($activePlan->isUnlimited(PlanLimit::MAX_USERS))->toBeFalse();
    } finally {
        teardownStarterTenant($tenant);
    }
});
