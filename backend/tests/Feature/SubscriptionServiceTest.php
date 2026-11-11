<?php

declare(strict_types=1);

use App\Exceptions\DowngradeBlockedException;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

/**
 * Find the first captured Monolog record whose message matches $message.
 *
 * @param  array<int, mixed>  $records
 * @return array{message: string, context: array<string, mixed>}|null
 */
function firstLogRecord(array $records, string $message): ?array
{
    foreach ($records as $record) {
        $msg = is_array($record) ? ($record['message'] ?? null) : ($record->message ?? null);
        if ($msg === $message) {
            $context = is_array($record) ? ($record['context'] ?? []) : ($record->context ?? []);

            return ['message' => $msg, 'context' => (array) $context];
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Task 5.9 — SubscriptionService plan change + usage protection + usage snapshot
|--------------------------------------------------------------------------
|
| Exercises plan changes against a real provisioned tenant (central subscription
| + per-tenant user count):
|   • upgrade/downgrade updates the subscription and audits the change (R6.1);
|   • a downgrade that would set a limit below current usage is BLOCKED and the
|     error names the violating resource (R6.2);
|   • a forced override applies the violating downgrade and records the forced
|     override with its reason (R6.3);
|   • QuotaService::usage() reports counts vs active limits for the tenant
|     (R5.5), including UNLIMITED (0/NULL) limits.
|
| DB-GATED: requires MySQL + CREATE DATABASE for stancl tenancy; skips when
| MySQL is unreachable, mirroring the provisioning + white-label suites.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision an isolated tenant on a given plan, returning [tenant, subscription].
 * The tenant DB is seeded with exactly one Company_Admin user, so the baseline
 * `max_users` usage count is 1.
 *
 * @return array{0: Tenant, 1: Subscription}
 */
function provisionSubscriptionTenant(Plan $plan, string $suffix): array
{
    $service = app(TenantProvisioningService::class);
    $result = $service->provision([
        'subdomain' => 'sub-'.$suffix,
        'plan_id' => $plan->getKey(),
        'currency' => 'USD',
        'company_name' => 'Sub Co '.$suffix,
        'admin_name' => 'Admin '.$suffix,
        'admin_email' => 'admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return [$result['tenant'], $result['subscription']];
}

function cleanupSubscriptionTenant(?Tenant $tenant): void
{
    if ($tenant === null || ! $tenant->exists) {
        return;
    }

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
    }
}

/** Create a plan with the given limits map. */
function makePlan(string $name, array $limits): Plan
{
    $plan = Plan::create(['name' => $name, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    foreach ($limits as $key => $value) {
        $plan->limits()->create(['key' => $key, 'value' => $value]);
    }

    return $plan->fresh('limits');
}

it('upgrades a tenant plan, updates the subscription, and audits the change (R6.1)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $starter = makePlan('Starter '.$suffix, [PlanLimit::MAX_USERS => 10]);
    $pro = makePlan('Pro '.$suffix, [PlanLimit::MAX_USERS => 100]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        [$tenant] = provisionSubscriptionTenant($starter, $suffix);

        // Capture the real central-audit emission path
        // (Log::channel(default)->info(...)) via a Monolog TestHandler.
        $testHandler = new TestHandler();
        Log::channel(config('logging.default'))->getLogger()->pushHandler($testHandler);

        $updated = app(SubscriptionService::class)->changePlan(
            $tenant,
            $pro,
            force: false,
            reason: 'customer upgraded',
        );

        expect($updated->plan_id)->toBe($pro->getKey());

        // Durable: the central subscription now points at the new plan (R6.1).
        $fresh = Subscription::query()->where('tenant_id', $tenant->getTenantKey())->latest('id')->first();
        expect($fresh->plan_id)->toBe($pro->getKey());

        // The change was recorded centrally (R6.1, R34.3).
        $record = firstLogRecord($testHandler->getRecords(), SubscriptionService::AUDIT_EVENT);
        expect($record)->not->toBeNull();
        expect((int) $record['context']['to_plan_id'])->toBe($pro->getKey());
        expect($record['context']['forced'])->toBeFalse();
    } finally {
        cleanupSubscriptionTenant($tenant);
        $pro->delete();
        $starter->delete();
    }
});

it('blocks a downgrade that drops a limit below current usage, naming the resource (R6.2)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $starter = makePlan('Starter '.$suffix, [PlanLimit::MAX_USERS => 10]);
    // Target plan sets max_users = 0? No — 0 means unlimited. Use a finite 0<limit<usage.
    // Baseline usage is 1 Company_Admin user, so a target of 0 is UNLIMITED (never violates);
    // we need a plan that forbids even a single user is impossible, so add a second user and
    // target a limit of 1 (below the usage of 2).
    $tiny = makePlan('Tiny '.$suffix, [PlanLimit::MAX_USERS => 1]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        [$tenant] = provisionSubscriptionTenant($starter, $suffix);

        // Add a second user so usage (2) exceeds the Tiny plan's limit (1).
        $tenant->run(function () use ($suffix) {
            User::create([
                'name' => 'Second '.$suffix,
                'email' => 'second-'.$suffix.'@example.test',
                'password' => 'password123',
            ]);
        });

        $service = app(SubscriptionService::class);

        expect(fn () => $service->changePlan($tenant, $tiny, force: false))
            ->toThrow(DowngradeBlockedException::class);

        // The subscription was left untouched by the blocked downgrade (R6.2).
        $fresh = Subscription::query()->where('tenant_id', $tenant->getTenantKey())->latest('id')->first();
        expect($fresh->plan_id)->toBe($starter->getKey());

        // The exception names the violating resource.
        try {
            $service->changePlan($tenant, $tiny, force: false);
        } catch (DowngradeBlockedException $e) {
            expect($e->violations)->toHaveKey(PlanLimit::MAX_USERS);
            expect($e->violations[PlanLimit::MAX_USERS]['count'])->toBe(2);
            expect($e->violations[PlanLimit::MAX_USERS]['target_limit'])->toBe(1);
            expect($e->getMessage())->toContain(PlanLimit::MAX_USERS);
        }
    } finally {
        cleanupSubscriptionTenant($tenant);
        $tiny->delete();
        $starter->delete();
    }
});

it('applies a forced downgrade that violates usage and audits the override with its reason (R6.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $starter = makePlan('Starter '.$suffix, [PlanLimit::MAX_USERS => 10]);
    $tiny = makePlan('Tiny '.$suffix, [PlanLimit::MAX_USERS => 1]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        [$tenant] = provisionSubscriptionTenant($starter, $suffix);

        $tenant->run(function () use ($suffix) {
            User::create([
                'name' => 'Second '.$suffix,
                'email' => 'second-'.$suffix.'@example.test',
                'password' => 'password123',
            ]);
        });

        // Capture the real central-audit emission path via a Monolog TestHandler.
        $testHandler = new TestHandler();
        Log::channel(config('logging.default'))->getLogger()->pushHandler($testHandler);

        $updated = app(SubscriptionService::class)->changePlan(
            $tenant,
            $tiny,
            force: true,
            reason: 'migration cleanup planned',
        );

        // Forced: the violating downgrade is applied (R6.3).
        expect($updated->plan_id)->toBe($tiny->getKey());

        $fresh = Subscription::query()->where('tenant_id', $tenant->getTenantKey())->latest('id')->first();
        expect($fresh->plan_id)->toBe($tiny->getKey());

        // The forced override + reason were recorded (R6.3, R34.3).
        $record = firstLogRecord($testHandler->getRecords(), SubscriptionService::AUDIT_EVENT);
        expect($record)->not->toBeNull();
        expect($record['context']['forced'])->toBeTrue();
        expect($record['context']['had_usage_violation'])->toBeTrue();
        expect($record['context']['reason'])->toBe('migration cleanup planned');
    } finally {
        cleanupSubscriptionTenant($tenant);
        $tiny->delete();
        $starter->delete();
    }
});

it('reports usage vs active plan limits for the tenant, including unlimited (R5.5)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    // max_users = 10 (finite), max_products = 0 (UNLIMITED per R4.4).
    $plan = makePlan('Snap '.$suffix, [PlanLimit::MAX_USERS => 10, PlanLimit::MAX_PRODUCTS => 0]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        [$tenant] = provisionSubscriptionTenant($plan, $suffix);

        $snapshot = $tenant->run(fn () => app(QuotaService::class)->usage());

        expect($snapshot['plan']['id'])->toBe($plan->getKey());
        expect($snapshot['subscription_status'])->toBe(Subscription::STATUS_ACTIVE);

        // Finite limit: usage count 1 (seeded Company_Admin) vs limit 10.
        $users = $snapshot['quotas'][PlanLimit::MAX_USERS];
        expect($users['count'])->toBe(1)
            ->and($users['limit'])->toBe(10)
            ->and($users['unlimited'])->toBeFalse()
            ->and($users['remaining'])->toBe(9);

        // Unlimited limit (0/NULL): no cap, remaining is null.
        $products = $snapshot['quotas'][PlanLimit::MAX_PRODUCTS];
        expect($products['unlimited'])->toBeTrue()
            ->and($products['limit'])->toBeNull()
            ->and($products['remaining'])->toBeNull();
    } finally {
        cleanupSubscriptionTenant($tenant);
        $plan->delete();
    }
});
