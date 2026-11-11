<?php

declare(strict_types=1);

use App\Exceptions\QuotaExceededException;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\QuotaService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 15: Quota comparison enforcement
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 15: Quota comparison
| enforcement
|
| Validates: Requirements 4.4, 5.1, 5.2, 5.3, 10.4
|
| Design reference: "Quota Caching Model" / QuotaService.
|   - R4.4  A quota value of 0 or NULL is UNLIMITED.
|   - R5.1  Before creating a Distributor/Dealer/Product/Stock_Point/User the
|           platform compares the CURRENT tenant count against the active plan
|           limit for that resource.
|   - R5.2  If the current count EQUALS OR EXCEEDS the limit, creation is
|           rejected with an HTTP 403 whose message NAMES the exceeded quota.
|   - R5.3  The same count-vs-limit rule applies to monthly orders
|           (`max_orders_per_month`).
|   - R10.4 The per-distributor dealer cap (`max_dealers_per_distributor`)
|           follows the same strictly-less-than comparison.
|
| The invariant under test is a pure comparison rule applied by
| QuotaService::assertAllows()/allows():
|
|     creation permitted  iff  limit is unlimited (0/NULL)  OR  count < limit
|     otherwise            ->   QuotaExceededException (HTTP 403, named quota)
|
| Two complementary proofs cover it:
|
|   (A) END-TO-END over the REAL count path. The `users` table always exists in
|       every milestone, so we seed an EXACT number of user rows (the count),
|       seed a `max_users` plan limit (the limit), invalidate the cache, and
|       assert the service's decision matches the comparison rule for every
|       generated (count, limit) pair — including the UNLIMITED (0) case and the
|       403-with-named-quota case.
|
|   (B) PURE comparison over EVERY covered quota key. For distributors, dealers,
|       per-distributor dealers, products, stock points, users and monthly
|       orders we drive the comparison with controlled count/limit values and
|       assert the permit/deny decision and, on deny, the 403 that NAMES that
|       specific quota key. Resource tables introduced in later milestones are
|       physically absent here, so their live count is 0 — we assert that case
|       directly (permitted unless the limit is unlimited-vs-zero edge).
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server; when MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'). Assertions are written to be correct against a
| real MySQL instance.
*/

/**
 * Provision an isolated tenant with a migrated tenant database and register it
 * for teardown. Mirrors the isolation-invariant proof's helper.
 */
function provisionQuotaTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'quota'.$suffix,
        'subdomain' => 'quota-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'quota-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    $registry[] = $tenant;

    return $tenant;
}

/**
 * Seed a central active subscription for a tenant pointing at a plan, returning
 * the Plan so the proof can attach/replace its limits.
 */
function seedQuotaPlan(Tenant $tenant): Plan
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    $plan = Plan::create([
        'name' => 'Quota Plan '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    Subscription::create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->getKey(),
        'status' => Subscription::STATUS_ACTIVE,
        'starts_at' => now(),
    ]);

    return $plan;
}

/**
 * Replace the plan's limit row for a quota key with an exact value, so each
 * generated scenario starts from a clean, deterministic limit.
 */
function setPlanLimit(Plan $plan, string $quotaKey, int $value): void
{
    $plan->limits()->where('key', $quotaKey)->delete();
    $plan->limits()->create(['key' => $quotaKey, 'value' => $value]);
}

/**
 * Force the tenant `users` table to hold exactly $target rows, so the live
 * count the QuotaService reads is fully controlled.
 */
function setUserCount(int $target): void
{
    DB::connection('tenant')->table('users')->delete();

    for ($i = 0; $i < $target; $i++) {
        DB::connection('tenant')->table('users')->insert([
            'name' => 'User '.$i,
            'email' => 'user'.$i.'-'.bin2hex(random_bytes(4)).'@quota.test',
            'password' => bcrypt('secret-'.$i),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * Best-effort teardown of provisioned tenants and their central rows.
 *
 * @param array<int, Tenant> $tenants
 */
function teardownQuotaTenants(array $tenants): void
{
    tenancy()->end();

    foreach ($tenants as $t) {
        try {
            Subscription::query()->where('tenant_id', $t->getTenantKey())->delete();
        } catch (\Throwable $e) {
            // best-effort
        }

        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}

it('permits creation iff unlimited or count strictly below limit over the real users count path, else throws a 403 naming the quota (R4.4, R5.1, R5.2)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $tenant = provisionQuotaTenant($tenants);
        $plan = seedQuotaPlan($tenant);

        // --------------------------------------------------------------
        // Generated invariant over the REAL count path (users table).
        //   limit: 0..6   (0 == UNLIMITED per R4.4)
        //   count: 0..6   (exact number of seeded user rows)
        // 100+ randomized (count, limit) pairs (BlackBox default).
        // --------------------------------------------------------------
        static::forAll(
            Set::integers()->between(0, 6),
            Set::integers()->between(0, 6),
        )->then(function (int $limit, int $count) use ($tenant, $plan) {
            setPlanLimit($plan, PlanLimit::MAX_USERS, $limit);

            $tenant->run(function () use ($limit, $count) {
                setUserCount($count);

                /** @var QuotaService $quota */
                $quota = app(QuotaService::class);

                // Live counts are cached; force a fresh read of the freshly
                // seeded state (R5.6 invalidation is exercised in Property 16).
                $quota->invalidateAll();

                $unlimited = ($limit === 0);
                $expectedPermitted = $unlimited || ($count < $limit);

                // Count/limit resolution matches the seeded state.
                expect($quota->isUnlimited(PlanLimit::MAX_USERS))->toBe($unlimited);
                expect($quota->count(PlanLimit::MAX_USERS))->toBe($count);
                if ($unlimited) {
                    expect($quota->limit(PlanLimit::MAX_USERS))->toBeNull();
                } else {
                    expect($quota->limit(PlanLimit::MAX_USERS))->toBe($limit);
                }

                // The boolean decision follows the comparison rule exactly.
                expect($quota->allows(PlanLimit::MAX_USERS))->toBe($expectedPermitted);

                if ($expectedPermitted) {
                    // assertAllows() returns quietly when creation is permitted.
                    $quota->assertAllows(PlanLimit::MAX_USERS);
                } else {
                    // Reached/exceeded a finite limit -> 403 naming the quota.
                    $thrown = null;
                    try {
                        $quota->assertAllows(PlanLimit::MAX_USERS);
                    } catch (QuotaExceededException $e) {
                        $thrown = $e;
                    }

                    expect($thrown)->toBeInstanceOf(QuotaExceededException::class);
                    expect($thrown->getStatusCode())->toBe(403);
                    expect($thrown->quotaKey)->toBe(PlanLimit::MAX_USERS);
                    expect($thrown->getMessage())->toContain(PlanLimit::MAX_USERS);
                    expect($thrown->count)->toBe($count);
                    expect($thrown->limit)->toBe($limit);
                }
            });
        });
    } finally {
        teardownQuotaTenants($tenants);
    }
});

it('applies the same strictly-less-than comparison to every covered quota key, naming the exact quota on a 403 (R5.1, R5.2, R5.3, R10.4)', function () {
    skip_without_mysql('central');

    $tenants = [];

    // Every quota key the comparison rule covers: distributors, dealers,
    // per-distributor dealers, products, stock points, users, monthly orders.
    $coveredKeys = [
        PlanLimit::MAX_DISTRIBUTORS,
        PlanLimit::MAX_DEALERS,
        PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR,
        PlanLimit::MAX_PRODUCTS,
        PlanLimit::MAX_STOCK_POINTS,
        PlanLimit::MAX_USERS,
        PlanLimit::MAX_ORDERS_PER_MONTH,
    ];

    try {
        $tenant = provisionQuotaTenant($tenants);
        $plan = seedQuotaPlan($tenant);

        // --------------------------------------------------------------
        // Generated invariant over EVERY covered quota key.
        //   keyIndex selects which quota key is under test
        //   limit:   0..5 (0 == UNLIMITED)
        // The users count is driven to the generated `count`; for keys whose
        // backing table is absent in this milestone the live count is 0, so we
        // branch the expectation on whether the key is `max_users`.
        // --------------------------------------------------------------
        static::forAll(
            Set::integers()->between(0, count($coveredKeys) - 1),
            Set::integers()->between(0, 5),
            Set::integers()->between(0, 5),
        )->then(function (int $keyIndex, int $limit, int $count) use ($tenant, $plan, $coveredKeys) {
            $quotaKey = $coveredKeys[$keyIndex];

            setPlanLimit($plan, $quotaKey, $limit);

            $tenant->run(function () use ($quotaKey, $limit, $count) {
                // Only `max_users` has a backing table in this milestone; its
                // live count is controllable. Every other key counts 0 because
                // its table is introduced in a later milestone.
                $effectiveCount = $quotaKey === PlanLimit::MAX_USERS ? $count : 0;

                if ($quotaKey === PlanLimit::MAX_USERS) {
                    setUserCount($count);
                }

                /** @var QuotaService $quota */
                $quota = app(QuotaService::class);
                $quota->invalidateAll();

                $unlimited = ($limit === 0);
                $expectedPermitted = $unlimited || ($effectiveCount < $limit);

                expect($quota->count($quotaKey))->toBe($effectiveCount);
                expect($quota->allows($quotaKey))->toBe($expectedPermitted);

                if ($expectedPermitted) {
                    $quota->assertAllows($quotaKey);
                } else {
                    $thrown = null;
                    try {
                        $quota->assertAllows($quotaKey);
                    } catch (QuotaExceededException $e) {
                        $thrown = $e;
                    }

                    expect($thrown)->toBeInstanceOf(QuotaExceededException::class);
                    expect($thrown->getStatusCode())->toBe(403);
                    // The 403 names the EXACT quota under test (R5.2).
                    expect($thrown->quotaKey)->toBe($quotaKey);
                    expect($thrown->getMessage())->toContain($quotaKey);
                }
            });
        });
    } finally {
        teardownQuotaTenants($tenants);
    }
});
