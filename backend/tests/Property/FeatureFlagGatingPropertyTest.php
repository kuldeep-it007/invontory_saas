<?php

declare(strict_types=1);

use App\Exceptions\FeatureDisabledException;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 5.8 — Property 19: Feature-flag gating
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 19: Feature-flag gating
|
| Validates: Requirements 5.4, 7.5, 23.2, 27.5, 30.6, 30.7
|
| Property statement (design.md §Property 19):
|   For any optional module gated by a feature flag, access is permitted if and
|   only if the flag is enabled; AI reporting is permitted if and only if BOTH
|   the AI_REPORTS_ENABLED master switch AND the tenant `ai_reports` flag are
|   enabled, otherwise the request is denied with 403.
|
| The gate (App\Services\SubscriptionGateService) reads per-plan feature flags
| from the CENTRAL database, so these proofs seed a central plan + subscription
| and drive the gate with an explicit tenant id. MySQL is not reachable in every
| environment, so every proof is DB-gated via skip_without_mysql('central') and
| skips gracefully when MySQL is down, mirroring the SubscriptionGateService
| unit suite and the provisioning/white-label suites.
|
| Generation uses innmind/black-box (NOT hand-rolled): BlackBox `Set`s produce
| the (flag, enabled) and (master-switch, tenant-flag) scenarios, and
| `->take(100)->enumerate()` draws the minimum 100 generated iterations the spec
| mandates. Central rows are torn down after each proof.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Seed a central plan carrying exactly the given feature flags plus an active
 * subscription for a synthetic tenant id, returning [tenantId, cleanup].
 *
 * `subscriptions.tenant_id` carries a REAL foreign key to `tenants.id`
 * (cascade on update/delete), so the subscription cannot exist without a
 * matching central `tenants` registry row. We therefore seed a bare registry
 * row FIRST (directly on the central connection, so no per-tenant database is
 * provisioned — the gate only reads the central plans/subscriptions rows by
 * tenant id). The id/subdomain are randomized so repeated or interleaved
 * iterations never collide on the `tenants`/`subscriptions` tables.
 *
 * Teardown deletes strictly child-before-parent — subscription, then the
 * plan's features/limits and the plan, then the tenant registry row — so a
 * failed or partial iteration cannot leak a row that an FK (or the UNIQUE
 * subdomain) would collide with on the next iteration. Teardown is also
 * idempotent and keyed by the generated id, so it is safe to call twice.
 *
 * @param  array<string, bool>  $features  flag key => enabled
 * @return array{0:string,1:Closure}
 */
function seedGatedPlan(array $features): array
{
    $suffix = strtolower(bin2hex(random_bytes(5)));
    $tenantId = 'flag-tenant-'.$suffix;

    // Defensive: clear any orphan rows from a prior crashed iteration that
    // happened to generate the same id (astronomically unlikely, but keeps
    // teardown races from ever leaking into a fresh seed).
    DB::connection('central')->table('subscriptions')->where('tenant_id', $tenantId)->delete();
    DB::connection('central')->table('tenants')->where('id', $tenantId)->delete();

    // Parent registry row (FK target for subscriptions.tenant_id). Inserted
    // directly on the central connection so stancl/tenancy does NOT provision a
    // real per-tenant database for this synthetic gate-only tenant.
    DB::connection('central')->table('tenants')->insert([
        'id' => $tenantId,
        'subdomain' => 'flag-'.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $plan = Plan::create([
        'name' => 'Flag Plan '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    foreach ($features as $key => $enabled) {
        $plan->features()->create(['key' => $key, 'enabled' => $enabled]);
    }

    $subscription = Subscription::create([
        'tenant_id' => $tenantId,
        'plan_id' => $plan->getKey(),
        'status' => Subscription::STATUS_ACTIVE,
        'starts_at' => now(),
    ]);

    $cleanup = function () use ($plan, $subscription, $tenantId): void {
        try {
            // Child-before-parent: subscription first (it FKs both tenants and
            // plans), then the plan's children and the plan, then the tenant.
            Subscription::query()->whereKey($subscription->getKey())->delete();
            DB::connection('central')->table('subscriptions')->where('tenant_id', $tenantId)->delete();

            $plan->features()->delete();
            $plan->limits()->delete();
            DB::connection('central')->table('plans')->where('id', $plan->getKey())->delete();

            // Parent registry row last, once nothing references it.
            DB::connection('central')->table('tenants')->where('id', $tenantId)->delete();
        } catch (\Throwable $e) {
            // best-effort teardown
        }
    };

    return [$tenantId, $cleanup];
}

/*
|--------------------------------------------------------------------------
| Part 1 — Optional-module flag gating (R5.4, R7.5, R23.2, R27.5)
|--------------------------------------------------------------------------
|
| For any non-AI optional module, access is permitted iff its per-plan flag is
| enabled; a disabled (or absent) flag denies with a 403. The feature keys here
| cover the optional modules the cited requirements gate (OTP login — R7.5;
| WhatsApp/channel notifications — R23.2, R27.5; plus exports / advance_orders /
| api_access as representative optional modules under R5.4).
*/

it('permits an optional module iff its feature flag is enabled, else denies 403 (R5.4, R7.5, R23.2, R27.5)', function () {
    // Non-AI optional-module flags only; the AI flag has a composite gate
    // exercised separately below.
    $flagKeys = array_values(array_filter(
        PlanFeature::FEATURE_KEYS,
        static fn (string $key): bool => $key !== PlanFeature::AI_REPORTS,
    ));

    // BlackBox generates random (flag, enabled?) scenarios across the module set.
    $scenarios = Set::tuple(
        Set::of(...$flagKeys),
        Set::of(true, false),
    )->take(100);

    $iterations = 0;

    foreach ($scenarios->enumerate() as [$flag, $enabled]) {
        $iterations++;

        [$tenantId, $cleanup] = seedGatedPlan([$flag => $enabled]);

        try {
            $gate = app(SubscriptionGateService::class);

            // IFF direction: the boolean decision equals the stored flag state.
            expect($gate->featureEnabled($flag, $tenantId))->toBe(
                $enabled,
                sprintf('Flag [%s] access must equal its enabled state (%s).', $flag, var_export($enabled, true)),
            );

            // The assert* companion throws a 403 exactly when the flag is off.
            $thrown = null;
            try {
                $gate->assertFeatureEnabled($flag, $tenantId);
            } catch (FeatureDisabledException $e) {
                $thrown = $e;
            }

            if ($enabled) {
                expect($thrown)->toBeNull(sprintf('Enabled flag [%s] must not throw.', $flag));
            } else {
                expect($thrown)->toBeInstanceOf(FeatureDisabledException::class);
                expect($thrown->getStatusCode())->toBe(403);
                expect($thrown->feature)->toBe($flag);
            }
        } finally {
            $cleanup();
        }
    }

    // Guard the spec's minimum-iteration requirement.
    expect($iterations)->toBeGreaterThanOrEqual(100);
});

/*
|--------------------------------------------------------------------------
| Part 2 — AI reporting composite gate (R30.6, R30.7)
|--------------------------------------------------------------------------
|
| AI reporting is permitted IFF the AI_REPORTS_ENABLED env master switch AND the
| tenant `ai_reports` plan flag are BOTH enabled. Any other combination denies
| with a 403. We drive the master switch via config (services.ai.reports_enabled,
| populated from the env var) and the tenant flag via the seeded plan, then
| assert the decision equals the logical AND across all four combinations.
*/

it('permits AI reporting iff both the master switch and the tenant flag are enabled, else denies 403 (R30.6, R30.7)', function () {
    // BlackBox generates random (masterSwitch, tenantFlag) combinations.
    $scenarios = Set::tuple(
        Set::of(true, false),
        Set::of(true, false),
    )->take(100);

    $iterations = 0;
    $sawBothOn = false;

    foreach ($scenarios->enumerate() as [$masterOn, $tenantFlagOn]) {
        $iterations++;

        // Flip the platform master switch for this iteration (R30.6).
        config()->set('services.ai.reports_enabled', $masterOn);

        [$tenantId, $cleanup] = seedGatedPlan([PlanFeature::AI_REPORTS => $tenantFlagOn]);

        try {
            $gate = app(SubscriptionGateService::class);

            $expected = $masterOn && $tenantFlagOn;
            if ($expected) {
                $sawBothOn = true;
            }

            // IFF direction: AI permitted exactly when BOTH switches are on.
            expect($gate->aiReportingEnabled($tenantId))->toBe(
                $expected,
                sprintf(
                    'AI reporting must be permitted iff master(%s) AND tenant-flag(%s).',
                    var_export($masterOn, true),
                    var_export($tenantFlagOn, true),
                ),
            );

            // The assert* companion throws a 403 exactly when either switch is off.
            $thrown = null;
            try {
                $gate->assertAiReportingEnabled($tenantId);
            } catch (FeatureDisabledException $e) {
                $thrown = $e;
            }

            if ($expected) {
                expect($thrown)->toBeNull('AI reporting must not throw when both switches are on.');
            } else {
                expect($thrown)->toBeInstanceOf(FeatureDisabledException::class);
                expect($thrown->getStatusCode())->toBe(403);
                expect($thrown->feature)->toBe(PlanFeature::AI_REPORTS);
            }
        } finally {
            $cleanup();
            config()->set('services.ai.reports_enabled', false);
        }
    }

    // Guard the spec's minimum-iteration requirement and that the permit path
    // (both-on) was actually observed at least once across the randomized runs.
    expect($iterations)->toBeGreaterThanOrEqual(100);
    expect($sawBothOn)->toBeTrue('Randomized scenarios must include at least one both-switches-on case.');
});
