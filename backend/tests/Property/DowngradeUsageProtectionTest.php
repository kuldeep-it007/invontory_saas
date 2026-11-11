<?php

declare(strict_types=1);

use App\Exceptions\DowngradeBlockedException;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 5.10 — Property 18: Downgrade usage protection
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 18: Downgrade usage protection
|
| Validates: Requirements 6.2, 6.3
|
| Property statement (design.md §Property 18):
|   For any current usage and target plan limit, a plan downgrade is blocked
|   (with a message naming the violating resource) if and only if the target
|   limit is below current usage and the change is NOT explicitly forced.
|
| The authority under test is App\Services\SubscriptionService::changePlan()
| (task 5.9): it reads the tenant's CURRENT usage from the per-tenant database
| and the TARGET plan's limits from the central database, then
|   • throws a 422 DowngradeBlockedException naming every violating resource
|     when a finite target limit is strictly below current usage AND the change
|     is not forced (R6.2), leaving the subscription untouched; or
|   • applies the change (updating the central subscription) otherwise —
|     including a FORCED violating downgrade (R6.3).
|
| "Below usage" means a FINITE target limit strictly less than the current
| count; moving to UNLIMITED (0/NULL) never violates (R4.4), so forcing is
| irrelevant there.
|
| The decision therefore depends on a real per-tenant user count and a real
| central subscription/plan, so this property is DB-GATED
| (skip_without_mysql('central')) and skips gracefully when MySQL is
| unreachable, mirroring the DB-gated QuotaComparisonEnforcement / provisioning
| suites. It is correct against a real MySQL instance. Every provisioned tenant
| and its central rows are torn down best-effort in a finally block so repeated
| or parallel runs never leak state.
|
| Inputs are generated with innmind/black-box (NOT hand-rolled): the current
| usage count, the target plan limit, and the forced flag are drawn from
| BlackBox `Set`s and the proof runs the framework default of 100 randomized
| iterations per proof (satisfying the minimum-100 requirement).
*/

/**
 * Provision an isolated tenant with a migrated tenant database plus an active
 * central subscription on a baseline (generous) plan, registering it for
 * teardown. Mirrors the quota-enforcement proof's helper.
 *
 * @param  array<int, Tenant>  $registry
 * @return array{0: Tenant, 1: Plan}  The tenant and its baseline (current) plan.
 */
function provisionDowngradeTenant(array &$registry): array
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'dg'.$suffix,
        'subdomain' => 'dg-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'dg-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    // Baseline "current" plan: unlimited users so the tenant can hold any
    // generated user count before the downgrade is attempted.
    $current = Plan::create([
        'name' => 'Downgrade Baseline '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $current->limits()->create(['key' => PlanLimit::MAX_USERS, 'value' => 0]); // 0 == unlimited

    Subscription::create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $current->getKey(),
        'status' => Subscription::STATUS_ACTIVE,
        'starts_at' => now(),
    ]);

    $registry[] = $tenant;

    return [$tenant, $current];
}

/**
 * Build (or rebuild) a target plan whose `max_users` limit is exactly $limit.
 * A fresh plan per scenario keeps each generated case deterministic and
 * independent.
 */
function makeDowngradeTargetPlan(int $limit): Plan
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    $plan = Plan::create([
        'name' => 'Downgrade Target '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $plan->limits()->create(['key' => PlanLimit::MAX_USERS, 'value' => $limit]);

    return $plan->fresh('limits');
}

/**
 * Force the tenant `users` table to hold exactly $target rows so the live usage
 * count SubscriptionService reads is fully controlled.
 */
function setDowngradeUserCount(int $target): void
{
    DB::connection('tenant')->table('users')->delete();

    for ($i = 0; $i < $target; $i++) {
        DB::connection('tenant')->table('users')->insert([
            'name' => 'User '.$i,
            'email' => 'user'.$i.'-'.bin2hex(random_bytes(4)).'@downgrade.test',
            'password' => bcrypt('secret-'.$i),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * Point the tenant's central subscription back at $plan, so each scenario
 * starts from a known "current" plan regardless of a previously applied
 * (forced) downgrade.
 */
function resetDowngradeSubscription(Tenant $tenant, Plan $plan): void
{
    Subscription::query()
        ->where('tenant_id', $tenant->getTenantKey())
        ->latest('id')
        ->first()
        ?->forceFill(['plan_id' => $plan->getKey()])
        ->save();
}

/**
 * Best-effort teardown of provisioned tenants and their central rows.
 *
 * @param  array<int, Tenant>  $tenants
 */
function teardownDowngradeTenants(array $tenants): void
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

it('blocks a downgrade naming the violating resource iff the target limit is below current usage and the change is not forced (Property 18, R6.2, R6.3)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        [$tenant, $currentPlan] = provisionDowngradeTenant($tenants);

        /** @var SubscriptionService $service */
        $service = app(SubscriptionService::class);

        // --------------------------------------------------------------
        // Generated invariant.
        //   usage:  1..6   (exact number of tenant user rows)
        //   limit:  0..6   (target max_users; 0 == UNLIMITED per R4.4)
        //   forced: bool   (explicit override flag)
        // 100 randomized (usage, limit, forced) scenarios (BlackBox default).
        // --------------------------------------------------------------
        static::forAll(
            Set::integers()->between(1, 6),
            Set::integers()->between(0, 6),
            Set::of(true, false),
        )->then(function (int $usage, int $limit, bool $forced) use ($tenant, $currentPlan, $service) {
            // Start every scenario from the baseline plan so a prior forced
            // downgrade can never contaminate the next case.
            resetDowngradeSubscription($tenant, $currentPlan);

            $tenant->run(function () use ($usage) {
                setDowngradeUserCount($usage);
            });

            $targetPlan = makeDowngradeTargetPlan($limit);

            try {
                $unlimited = ($limit === 0);

                // The property's authority of record: a downgrade is blocked
                // IFF the finite target limit is strictly below current usage
                // AND the change is not forced.
                $violatesUsage = ! $unlimited && ($limit < $usage);
                $shouldBlock = $violatesUsage && ! $forced;

                $thrown = null;
                $updated = null;
                try {
                    $updated = $service->changePlan($tenant, $targetPlan, force: $forced);
                } catch (DowngradeBlockedException $e) {
                    $thrown = $e;
                }

                if ($shouldBlock) {
                    // Blocked (R6.2): a 422 is raised, it NAMES the violating
                    // resource, and the subscription is left on the old plan.
                    expect($thrown)->toBeInstanceOf(DowngradeBlockedException::class);
                    expect($thrown->getStatusCode())->toBe(422);
                    expect($thrown->violations)->toHaveKey(PlanLimit::MAX_USERS);
                    expect($thrown->violations[PlanLimit::MAX_USERS]['count'])->toBe($usage);
                    expect($thrown->violations[PlanLimit::MAX_USERS]['target_limit'])->toBe($limit);
                    expect($thrown->getMessage())->toContain(PlanLimit::MAX_USERS);

                    $fresh = Subscription::query()
                        ->where('tenant_id', $tenant->getTenantKey())
                        ->latest('id')
                        ->first();
                    expect($fresh->plan_id)->toBe($currentPlan->getKey());
                } else {
                    // Applied (R6.3 for a forced violation; R6.1 otherwise):
                    // no block, and the subscription now points at the target.
                    expect($thrown)->toBeNull(sprintf(
                        'Downgrade must NOT be blocked for usage=%d, limit=%d, forced=%s.',
                        $usage,
                        $limit,
                        $forced ? 'true' : 'false',
                    ));
                    expect($updated->plan_id)->toBe($targetPlan->getKey());

                    $fresh = Subscription::query()
                        ->where('tenant_id', $tenant->getTenantKey())
                        ->latest('id')
                        ->first();
                    expect($fresh->plan_id)->toBe($targetPlan->getKey());
                }
            } finally {
                // Reset to the baseline and drop the per-scenario target plan so
                // central rows never accumulate across iterations.
                resetDowngradeSubscription($tenant, $currentPlan);
                try {
                    $targetPlan->limits()->delete();
                    $targetPlan->delete();
                } catch (\Throwable $e) {
                    // best-effort
                }
            }
        });
    } finally {
        teardownDowngradeTenants($tenants);
    }
});
