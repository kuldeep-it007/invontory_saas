<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImpersonationService;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Unit (DB-gated): ImpersonationService — scoped token + audit (R8.6, R34.3)
|--------------------------------------------------------------------------
|
| Formal task 3.7 unit coverage of App\Services\ImpersonationService. Where the
| Smoke test (task 3.6) proves the happy path exists end-to-end, this suite
| asserts the SERVICE's contract PRECISELY and in isolation:
|
|  R8.6 — impersonation issues a short-lived token SCOPED/BOUND to the target
|         tenant:
|           * the token row is written only in the TARGET tenant's database and
|             is stamped with that tenant's id (cannot be replayed elsewhere);
|           * the token's ability set is EXACTLY the `impersonation` marker plus
|             the target user's own abilities (deduped), nothing more;
|           * the token's `expires_at` sits in a tight ~TOKEN_TTL_MINUTES (30)
|             window from "now", and the returned expiry matches the stored one.
|
|  R34.3 — the impersonation session is recorded in the TARGET tenant's audit
|          log with the actor (Platform_Admin), the target tenant + user, and
|          the reason; the audited expiry equals the issued token's expiry.
|
|  Missing target user → NotFoundHttpException (mapped to HTTP 404).
|
| DB-GATED: provisioning a tenant needs a reachable MySQL server with CREATE
| DATABASE privileges. When MySQL is unreachable each test is reported SKIPPED,
| never failed; the assertions are written to be correct against real MySQL.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision an isolated tenant for an impersonation unit test and return it.
 */
function impersonationUnitTenant(string $suffix, int $planId): Tenant
{
    /** @var array{tenant: Tenant} $result */
    $result = app(TenantProvisioningService::class)->provision([
        'subdomain' => 'impu-'.$suffix,
        'plan_id' => $planId,
        'currency' => 'USD',
        'company_name' => 'Imp Unit Co '.$suffix,
        'admin_name' => 'Admin '.$suffix,
        'admin_email' => 'admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return $result['tenant'];
}

/**
 * Best-effort teardown of a provisioned tenant (database + central rows).
 */
function cleanupImpersonationUnitTenant(?Tenant $tenant): void
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

it('issues a token whose ability set is EXACTLY the marker plus the target user abilities, with a ~30-minute expiry stamped to the target tenant (R8.6)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $tenant = impersonationUnitTenant($suffix, $plan->getKey());

        // Resolve the seeded Company_Admin as the impersonation target and
        // capture the EXACT ability set we expect the token to carry.
        [$targetUserId, $expectedAbilities] = $tenant->run(function () {
            /** @var User $target */
            $target = User::query()->firstOrFail();

            $expected = array_values(array_unique(array_merge(
                [ImpersonationService::IMPERSONATION_ABILITY],
                $target->abilities(),
            )));

            return [$target->getKey(), $expected];
        });

        // The target Company_Admin should actually hold abilities, otherwise
        // the "marker + target abilities" assertion would be vacuous.
        expect(count($expectedAbilities))->toBeGreaterThan(1);

        $before = Carbon::now();
        $result = app(ImpersonationService::class)->impersonate(
            $tenant,
            $targetUserId,
            null,
            'incident #4242',
        );
        $after = Carbon::now();

        // Return contract (R8.6): plain-text token, future expiry, bound tenant.
        expect($result['token'])->toBeString()->not->toBe('');
        expect($result['tenant_id'])->toBe($tenant->getTenantKey());
        expect($result['target_user']['id'])->toBe($targetUserId);

        // The returned expiry sits in a tight TTL window around issuance:
        // (now_before + 30m) .. (now_after + 30m). This proves the token is
        // SHORT-LIVED and anchored to the 30-minute TTL, not merely "future".
        $windowStart = (clone $before)->addMinutes(ImpersonationService::TOKEN_TTL_MINUTES);
        $windowEnd = (clone $after)->addMinutes(ImpersonationService::TOKEN_TTL_MINUTES);
        expect($result['expires_at']->greaterThanOrEqualTo($windowStart))->toBeTrue();
        expect($result['expires_at']->lessThanOrEqualTo($windowEnd))->toBeTrue();

        // Inspect the physical token row inside the TARGET tenant's database.
        $tenant->run(function () use ($targetUserId, $expectedAbilities, $result) {
            /** @var PersonalAccessToken $token */
            $token = PersonalAccessToken::query()->latest('id')->firstOrFail();

            // Bound to the target user, physically present only in this tenant DB.
            expect($token->tokenable_id)->toBe($targetUserId);

            // EXACT ability set: marker + target abilities, nothing more/less.
            $tokenAbilities = $token->abilities ?? [];
            sort($tokenAbilities);
            $expectedSorted = $expectedAbilities;
            sort($expectedSorted);
            expect($tokenAbilities)->toBe($expectedSorted);
            expect($token->can(ImpersonationService::IMPERSONATION_ABILITY))->toBeTrue();

            // Stamped to the active (target) tenant so ReconcileTenantUser
            // rejects any cross-tenant replay (R8.6 scoping/binding).
            expect($token->getAttribute('tenant_id'))->toBe(tenant('id'));

            // Stored expiry matches the returned expiry to the second (one
            // coherent short-lived window, not two independent clocks).
            expect($token->expires_at)->not->toBeNull();
            expect($token->expires_at->toIso8601String())
                ->toBe($result['expires_at']->toIso8601String());
        });
    } finally {
        cleanupImpersonationUnitTenant($tenant);
        $plan->delete();
    }
});

it('records the impersonation session in the tenant audit log with actor, target tenant + user, reason, and matching expiry (R34.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $tenant = impersonationUnitTenant($suffix, $plan->getKey());

        $targetUserId = $tenant->run(fn () => User::query()->firstOrFail()->getKey());

        // A concrete Platform_Admin actor (central user) so the audit entry's
        // actor fields can be asserted precisely rather than as nulls.
        $actor = new User;
        $actor->forceFill([
            'id' => 7777,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@central.test',
        ]);

        $reason = 'customer-reported stock discrepancy';

        $result = app(ImpersonationService::class)->impersonate(
            $tenant,
            $targetUserId,
            $actor,
            $reason,
        );

        $tenant->run(function () use ($actor, $targetUserId, $reason, $result, $tenant) {
            /** @var AuditLog $audit */
            $audit = AuditLog::query()
                ->where('event', ImpersonationService::AUDIT_EVENT)
                ->latest('id')
                ->firstOrFail();

            $values = $audit->new_values ?? [];

            // Actor = the Platform_Admin that performed the impersonation.
            expect($values['actor_type'] ?? null)->toBe($actor::class);
            expect($values['actor_id'] ?? null)->toBe($actor->getKey());
            expect($values['actor_email'] ?? null)->toBe('platform-admin@central.test');

            // Target tenant + user recorded on the entry.
            expect($values['tenant_id'] ?? null)->toBe($tenant->getTenantKey());
            expect($values['target_user_id'] ?? null)->toBe($targetUserId);
            expect($values['target_user_email'] ?? null)->toBeString()->not->toBe('');

            // The reason is recorded verbatim (R34.3 — "the action and its reason").
            expect($values['reason'] ?? null)->toBe($reason);

            // The audited expiry equals the issued token's expiry: one coherent
            // short-lived session window across token + audit.
            expect($values['expires_at'] ?? null)->toBe($result['expires_at']->toIso8601String());
        });
    } finally {
        cleanupImpersonationUnitTenant($tenant);
        $plan->delete();
    }
});

it('defaults the audited reason when none is supplied (R34.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $tenant = impersonationUnitTenant($suffix, $plan->getKey());

        $targetUserId = $tenant->run(fn () => User::query()->firstOrFail()->getKey());

        app(ImpersonationService::class)->impersonate($tenant, $targetUserId, null, null);

        $tenant->run(function () {
            /** @var AuditLog $audit */
            $audit = AuditLog::query()
                ->where('event', ImpersonationService::AUDIT_EVENT)
                ->latest('id')
                ->firstOrFail();

            // Even without a caller-supplied reason the session is still
            // recorded with a non-empty, explanatory default reason (R34.3).
            $reason = $audit->new_values['reason'] ?? null;
            expect($reason)->toBeString()->not->toBe('');
        });
    } finally {
        cleanupImpersonationUnitTenant($tenant);
        $plan->delete();
    }
});

it('rejects impersonating a non-existent tenant user with a NotFoundHttpException (404) and writes no impersonation token or audit entry (R8.6)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = Plan::create(['name' => 'Starter '.$suffix, 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);

    /** @var Tenant|null $tenant */
    $tenant = null;

    try {
        $tenant = impersonationUnitTenant($suffix, $plan->getKey());

        $missingUserId = 999999;

        // Baseline token/audit counts BEFORE the failed attempt.
        [$tokensBefore, $auditsBefore] = $tenant->run(fn () => [
            PersonalAccessToken::query()->count(),
            AuditLog::query()->where('event', ImpersonationService::AUDIT_EVENT)->count(),
        ]);

        expect(fn () => app(ImpersonationService::class)->impersonate($tenant, $missingUserId, null, null))
            ->toThrow(NotFoundHttpException::class);

        // A missing target produces NO scoped token and NO impersonation audit
        // entry — the failed attempt leaves the tenant DB untouched.
        $tenant->run(function () use ($tokensBefore, $auditsBefore) {
            expect(PersonalAccessToken::query()->count())->toBe($tokensBefore);
            expect(AuditLog::query()->where('event', ImpersonationService::AUDIT_EVENT)->count())
                ->toBe($auditsBefore);
        });
    } finally {
        cleanupImpersonationUnitTenant($tenant);
        $plan->delete();
    }
});
