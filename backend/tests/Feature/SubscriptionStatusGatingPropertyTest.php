<?php

declare(strict_types=1);

use App\Exceptions\SubscriptionInactiveException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 5.7 — Property 17: Subscription-status gating
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 17: Subscription-status gating
|
| Validates: Requirements 5.7
|
| Property statement (design.md §Property 17):
|   For any subscription status, creation of new tenant-owned resources is
|   denied with 403 if and only if the status is `past_due` or `cancelled`.
|
| The gate under test is App\Services\SubscriptionGateService (task 5.6): it
| resolves the active tenant's latest subscription from the CENTRAL database
| and throws a 403 SubscriptionInactiveException exactly when the status is
| `past_due`/`cancelled`. The decision therefore depends on a real central
| subscription row, so this property is DB-GATED (skip_without_mysql('central'))
| and skips gracefully when MySQL is unreachable, mirroring the DB-gated
| SubscriptionGateServiceTest / provisioning suites. It is correct against real
| MySQL. Each generated subscription is torn down in a finally block so
| repeated/parallel runs never leak central rows.
|
| Inputs are generated with innmind/black-box (NOT hand-rolled): the subscription
| status is drawn from BlackBox `Set`s spanning the full R4.6 status taxonomy
| plus arbitrary non-blocking strings, and the proof draws a minimum of 100
| generated iterations via `->take(100)->enumerate()`.
|
*/

/**
 * Minimum number of generated iterations required by the plan.
 */
const SUBSCRIPTION_STATUS_GATING_ITERATIONS = 100;

/**
 * The two statuses that, per R5.7, must block creation of new tenant-owned
 * resources with a 403. Every other status (including `active`, `trial`, and
 * any unknown value) must be permitted.
 *
 * @var list<string>
 */
const SUBSCRIPTION_BLOCKING_STATUSES = [
    Subscription::STATUS_PAST_DUE,
    Subscription::STATUS_CANCELLED,
];

/**
 * A black-box Set over the COMPLETE subscription-status input space.
 *
 * The central `subscriptions.status` column is an
 * `ENUM('active','past_due','cancelled','trial')` (migration
 * 2026_10_03_130200), so the four canonical R4.6 statuses are the only values
 * the system can ever persist — they ARE the entire domain of "any
 * subscription status" the property quantifies over. BlackBox draws randomly
 * from this domain across the required iterations, exercising both the two
 * blocking statuses (`past_due`, `cancelled`) and the two permitting statuses
 * (`active`, `trial`), so both directions of the iff are covered.
 *
 * @return Set<string>
 */
function subscriptionStatusSet(): Set
{
    return Set::of(
        Subscription::STATUS_ACTIVE,
        Subscription::STATUS_TRIAL,
        Subscription::STATUS_PAST_DUE,
        Subscription::STATUS_CANCELLED,
    );
}

/**
 * Seed a central plan + a subscription with the given status for a synthetic
 * tenant id, returning [tenantId, cleanup]. The tenant id is randomized so
 * repeated iterations never collide on the central subscriptions table.
 *
 * @return array{0:string,1:Closure}
 */
function seedSubscriptionWithStatus(string $status): array
{
    $suffix = strtolower(bin2hex(random_bytes(5)));
    $tenantId = 'gate17-'.$suffix;

    // subscriptions.tenant_id carries a real FK to central tenants.id, so the
    // referenced tenant row must exist first. Insert it directly (bypassing
    // model events / DB provisioning): this status gate is a central-only check.
    DB::connection('central')->table('tenants')->insert([
        'id' => $tenantId,
        'subdomain' => $tenantId,
        'currency' => 'USD',
        'data' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $plan = Plan::create([
        'name' => 'Gate17 Plan '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    $subscription = Subscription::create([
        'tenant_id' => $tenantId,
        'plan_id' => $plan->getKey(),
        'status' => $status,
        'starts_at' => now(),
    ]);

    $cleanup = function () use ($plan, $subscription, $tenantId): void {
        try {
            Subscription::query()->whereKey($subscription->getKey())->delete();
            DB::connection('central')->table('plans')->where('id', $plan->getKey())->delete();
            DB::connection('central')->table('tenants')->where('id', $tenantId)->delete();
        } catch (\Throwable $e) {
            // best-effort teardown
        }
    };

    return [$tenantId, $cleanup];
}

it('denies resource creation with a 403 iff the subscription status is past_due or cancelled (Property 17, R5.7)', function () {
    skip_without_mysql('central');

    /** @var SubscriptionGateService $gate */
    $gate = app(SubscriptionGateService::class);

    $checked = 0;

    foreach (subscriptionStatusSet()->take(SUBSCRIPTION_STATUS_GATING_ITERATIONS)->enumerate() as $status) {
        [$tenantId, $cleanup] = seedSubscriptionWithStatus($status);

        try {
            // The authority of record for this property: blocking ⇔ status is
            // one of the two named blocking statuses.
            $shouldBlock = in_array($status, SUBSCRIPTION_BLOCKING_STATUSES, true);

            // Non-throwing predicate agrees with the iff direction.
            expect($gate->resourceCreationAllowed($tenantId))->toBe(
                ! $shouldBlock,
                sprintf('Status [%s] creation-allowed decision must be [%s].', $status, $shouldBlock ? 'denied' : 'permitted'),
            );

            // Throwing assertion matches: a 403 is raised exactly when blocking.
            $thrown = null;
            try {
                $gate->assertResourceCreationAllowed($tenantId);
            } catch (SubscriptionInactiveException $e) {
                $thrown = $e;
            }

            if ($shouldBlock) {
                expect($thrown)->toBeInstanceOf(SubscriptionInactiveException::class);
                expect($thrown->getStatusCode())->toBe(403);
                expect($thrown->status)->toBe($status);
                expect($thrown->getMessage())->toContain($status);
            } else {
                expect($thrown)->toBeNull(
                    sprintf('Non-blocking status [%s] must not raise a 403.', $status),
                );
            }

            $checked++;
        } finally {
            $cleanup();
        }
    }

    // Guard: the generator really produced the required minimum iterations.
    expect($checked)->toBeGreaterThanOrEqual(SUBSCRIPTION_STATUS_GATING_ITERATIONS);
});
