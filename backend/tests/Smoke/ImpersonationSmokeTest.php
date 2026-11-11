<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImpersonationService;
use App\Services\TenantProvisioningService;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Smoke (DB-gated): ImpersonationService — scoped token + audit (R8.6, R34.3)
|--------------------------------------------------------------------------
|
| Minimal task 3.6 smoke coverage (the dedicated unit test is task 3.7):
|  - Impersonation initializes the TARGET tenant's database, issues a
|    short-lived Sanctum token carrying the `impersonation` marker ability and
|    an explicit expiry, physically bound to that tenant's token table (R8.6).
|  - The impersonation session is recorded in the TARGET tenant's audit log
|    with actor, target user, and reason (R34.3).
|  - A non-existent target user is rejected with a 404-mapped
|    NotFoundHttpException.
|
| DB-GATED: requires a reachable MySQL server with CREATE DATABASE privileges.
| When MySQL is unreachable each test is reported SKIPPED, never failed.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision an isolated tenant for an impersonation smoke test and return it.
 */
function impersonationTestTenant(string $suffix, int $planId): Tenant
{
    /** @var array{tenant: Tenant} $result */
    $result = app(TenantProvisioningService::class)->provision([
        'subdomain' => 'imp-'.$suffix,
        'plan_id' => $planId,
        'currency' => 'USD',
        'company_name' => 'Imp Co '.$suffix,
        'admin_name' => 'Admin '.$suffix,
        'admin_email' => 'admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return $result['tenant'];
}

/**
 * Best-effort teardown of a provisioned tenant (database + central rows).
 */
function cleanupImpersonationTenant(?Tenant $tenant): void
{
    if ($tenant === null || ! $tenant->exists) {
        return;
    }

    try {
        \App\Models\Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
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

it('issues a short-lived scoped token bound to the tenant and audits the session (R8.6, R34.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $tenant = impersonationTestTenant($suffix, $plan->getKey());

        // The target user is the seeded Company_Admin inside the tenant DB.
        $targetUserId = $tenant->run(fn () => User::query()->firstOrFail()->getKey());

        $result = app(ImpersonationService::class)->impersonate(
            $tenant,
            $targetUserId,
            null,
            'support debugging',
        );

        // A plain-text token is returned with an explicit short expiry (R8.6).
        expect($result['token'])->toBeString()->not->toBe('');
        expect($result['expires_at']->isFuture())->toBeTrue();
        expect($result['tenant_id'])->toBe($tenant->getTenantKey());
        expect($result['target_user']['id'])->toBe($targetUserId);

        // The token row lives in the TARGET tenant's database, carries the
        // impersonation marker ability, and has an expires_at (physical binding).
        $tenant->run(function () use ($targetUserId) {
            /** @var PersonalAccessToken $token */
            $token = PersonalAccessToken::query()->latest('id')->firstOrFail();

            expect($token->tokenable_id)->toBe($targetUserId);
            expect($token->can(ImpersonationService::IMPERSONATION_ABILITY))->toBeTrue();
            expect($token->expires_at)->not->toBeNull();
            // Stamped to the target tenant so it cannot be replayed elsewhere.
            expect($token->getAttribute('tenant_id'))->toBe(tenant('id'));

            // The impersonation session is recorded in the tenant audit log
            // with the reason (R34.3).
            $audit = AuditLog::query()
                ->where('event', ImpersonationService::AUDIT_EVENT)
                ->latest('id')
                ->firstOrFail();

            expect($audit->new_values['reason'] ?? null)->toBe('support debugging');
            expect($audit->new_values['target_user_id'] ?? null)->toBe($targetUserId);
        });
    } finally {
        cleanupImpersonationTenant($tenant);
        $plan->delete();
    }
});

it('rejects impersonating a non-existent tenant user with a 404 (R8.6)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $tenant = impersonationTestTenant($suffix, $plan->getKey());

        expect(fn () => app(ImpersonationService::class)->impersonate($tenant, 999999, null, null))
            ->toThrow(NotFoundHttpException::class);
    } finally {
        cleanupImpersonationTenant($tenant);
        $plan->delete();
    }
});
