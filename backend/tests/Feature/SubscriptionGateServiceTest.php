<?php

declare(strict_types=1);

use App\Exceptions\FeatureDisabledException;
use App\Exceptions\SubscriptionInactiveException;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 5.6 — Unit tests for SubscriptionGateService (R5.4, R5.7)
|--------------------------------------------------------------------------
|
| Exercise the subscription-status gate (R5.7 — deny creation of new tenant-
| owned resources with 403 when status is past_due/cancelled) and the feature-
| flag guard (R5.4 — deny a disabled optional module with 403). The service
| reads plan/subscription from the CENTRAL database, so each test seeds a plan
| + subscription centrally and passes the tenant id explicitly (avoiding the
| need to boot full tenancy for a unit-level check).
|
| MySQL is not reachable in every environment, so these are DB-gated via
| skip_without_mysql('central') and skip gracefully when MySQL is down,
| mirroring the provisioning/white-label suites. Central rows are torn down in
| a finally block.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Seed a central plan with the given feature flags and a subscription for a
 * synthetic tenant id, returning [tenantId, plan, subscription] plus a cleanup
 * closure. The tenant id is a random string so parallel/repeated runs never
 * collide on the central subscriptions table.
 *
 * @param  array<string, bool>  $features
 * @return array{0:string,1:Plan,2:Subscription,3:Closure}
 */
function seedCentralPlanAndSubscription(string $status, array $features = []): array
{
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $tenantId = 'gate-tenant-'.$suffix;

    // The central subscriptions.tenant_id carries a real FK to tenants.id, so
    // the referenced central tenant row must exist before the subscription is
    // inserted. Insert it directly (bypassing model events / DB provisioning)
    // because this unit-level gate check never boots the per-tenant database.
    DB::connection('central')->table('tenants')->insert([
        'id' => $tenantId,
        'subdomain' => $tenantId,
        'currency' => 'USD',
        'data' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $plan = Plan::create([
        'name' => 'Gate Plan '.$suffix,
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
        'status' => $status,
        'starts_at' => now(),
    ]);

    $cleanup = function () use ($plan, $subscription, $tenantId): void {
        try {
            Subscription::query()->whereKey($subscription->getKey())->delete();
            $plan->features()->delete();
            $plan->limits()->delete();
            DB::connection('central')->table('plans')->where('id', $plan->getKey())->delete();
            DB::connection('central')->table('tenants')->where('id', $tenantId)->delete();
        } catch (\Throwable $e) {
            // best-effort teardown
        }
    };

    return [$tenantId, $plan, $subscription, $cleanup];
}

/*
|--------------------------------------------------------------------------
| R5.7 — subscription-status gating of resource creation
|--------------------------------------------------------------------------
*/

it('permits resource creation when the subscription is active (R5.7)', function () {
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(Subscription::STATUS_ACTIVE);

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->resourceCreationAllowed($tenantId))->toBeTrue();
        // No exception is thrown for an active subscription.
        $gate->assertResourceCreationAllowed($tenantId);
    } finally {
        $cleanup();
    }
});

it('permits resource creation when the subscription is in trial (R5.7)', function () {
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(Subscription::STATUS_TRIAL);

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->resourceCreationAllowed($tenantId))->toBeTrue();
        $gate->assertResourceCreationAllowed($tenantId);
    } finally {
        $cleanup();
    }
});

it('denies resource creation with a 403 when the subscription is past_due (R5.7)', function () {
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(Subscription::STATUS_PAST_DUE);

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->resourceCreationAllowed($tenantId))->toBeFalse();

        $thrown = null;
        try {
            $gate->assertResourceCreationAllowed($tenantId);
        } catch (SubscriptionInactiveException $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeInstanceOf(SubscriptionInactiveException::class);
        expect($thrown->getStatusCode())->toBe(403);
        expect($thrown->status)->toBe(Subscription::STATUS_PAST_DUE);
        expect($thrown->getMessage())->toContain('past_due');
    } finally {
        $cleanup();
    }
});

it('denies resource creation with a 403 when the subscription is cancelled (R5.7)', function () {
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(Subscription::STATUS_CANCELLED);

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->resourceCreationAllowed($tenantId))->toBeFalse();

        $thrown = null;
        try {
            $gate->assertResourceCreationAllowed($tenantId);
        } catch (SubscriptionInactiveException $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeInstanceOf(SubscriptionInactiveException::class);
        expect($thrown->getStatusCode())->toBe(403);
        expect($thrown->status)->toBe(Subscription::STATUS_CANCELLED);
    } finally {
        $cleanup();
    }
});

it('does not block creation when the tenant has no subscription on record (R5.7 scope)', function () {
    $gate = app(SubscriptionGateService::class);

    // A tenant id with no central subscription row is not past_due/cancelled,
    // so the status gate does not block it (R5.7 is scoped to those statuses).
    expect($gate->resourceCreationAllowed('gate-tenant-missing-'.bin2hex(random_bytes(4))))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| R5.4 — feature-flag gating of optional modules
|--------------------------------------------------------------------------
*/

it('permits an optional module when its feature flag is enabled (R5.4)', function () {
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(
        Subscription::STATUS_ACTIVE,
        [PlanFeature::EXPORTS => true],
    );

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->featureEnabled(PlanFeature::EXPORTS, $tenantId))->toBeTrue();
        // No exception thrown when the flag is enabled.
        $gate->assertFeatureEnabled(PlanFeature::EXPORTS, $tenantId);
    } finally {
        $cleanup();
    }
});

it('denies an optional module with a 403 when its feature flag is disabled (R5.4)', function () {
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(
        Subscription::STATUS_ACTIVE,
        [PlanFeature::EXPORTS => false],
    );

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->featureEnabled(PlanFeature::EXPORTS, $tenantId))->toBeFalse();

        $thrown = null;
        try {
            $gate->assertFeatureEnabled(PlanFeature::EXPORTS, $tenantId);
        } catch (FeatureDisabledException $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeInstanceOf(FeatureDisabledException::class);
        expect($thrown->getStatusCode())->toBe(403);
        expect($thrown->feature)->toBe(PlanFeature::EXPORTS);
        expect($thrown->getMessage())->toContain(PlanFeature::EXPORTS);
    } finally {
        $cleanup();
    }
});

it('denies an optional module with a 403 when the plan has no row for the flag (R5.4 — off by default)', function () {
    // Plan seeded with NO feature rows at all; an absent flag is disabled.
    [$tenantId, , , $cleanup] = seedCentralPlanAndSubscription(Subscription::STATUS_ACTIVE);

    try {
        $gate = app(SubscriptionGateService::class);

        expect($gate->featureEnabled(PlanFeature::ADVANCE_ORDERS, $tenantId))->toBeFalse();

        $thrown = null;
        try {
            $gate->assertFeatureEnabled(PlanFeature::ADVANCE_ORDERS, $tenantId);
        } catch (FeatureDisabledException $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeInstanceOf(FeatureDisabledException::class);
        expect($thrown->getStatusCode())->toBe(403);
    } finally {
        $cleanup();
    }
});
