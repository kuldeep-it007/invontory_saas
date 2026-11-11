<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Platform_Admin impersonation of a tenant user (R8.6, R34.3).
 *
 * ## What impersonation is here
 *
 * A Platform_Admin — who lives in the CENTRAL database and operates on the
 * `platform.admin` route group WITHOUT tenancy initialization (R1.6) — needs to
 * act as a specific user inside a specific tenant's database for support/debug
 * purposes. Ordinary tenant isolation is physical (one database per tenant), so
 * the only sanctioned cross-tenant path for a Platform_Admin is this audited
 * impersonation that EXPLICITLY initializes the target tenant's database for the
 * duration of the impersonated session (R1.6, R8.6; design "Platform_Admin
 * exception").
 *
 * ## How the session is created
 *
 * Given a target tenant and a target user id, this service:
 *
 *   1. Resolves the {@see Tenant} registry row in the central database.
 *   2. Initializes that tenant's database via {@see Tenant::run()} so every
 *      subsequent read/write (user lookup, token issuance, audit) lands in the
 *      TARGET tenant's own database (R8.6).
 *   3. Verifies the target user exists in that tenant database; a missing user
 *      surfaces as HTTP 404 (NotFoundHttpException → centralized envelope).
 *   4. Issues a SHORT-LIVED Sanctum token on the target user, carrying the
 *      {@see self::IMPERSONATION_ABILITY} marker ability and the target user's
 *      own abilities, with an explicit `expires_at` {@see self::TOKEN_TTL_MINUTES}
 *      minutes out. The token is physically written to the target tenant's
 *      `personal_access_tokens` table, so it only ever resolves against — and is
 *      scoped to — that tenant's database when later presented on tenant routes.
 *   5. Records the impersonation session in the TARGET tenant's `audit_logs`
 *      with the actor (Platform_Admin), the target user, a reason, and the
 *      session's expiry (R34.3 — "impersonation ... recorded with reason").
 *
 * ## Why the token is bound to the tenant
 *
 * Sanctum resolves a bearer token's `tokenable` against the ACTIVE connection.
 * Because the token row is created while the target tenant DB is the active
 * connection, the token exists only in that tenant's database and can only be
 * resolved when a request is initialized for that same tenant — binding is
 * physical, exactly like the rest of the platform's isolation model. The token
 * is additionally stamped with the target tenant id (just as a normal login
 * stamps its token) so the {@see \App\Http\Middleware\ReconcileTenantUser}
 * guard rejects any attempt to replay the impersonation token against a
 * different tenant's database.
 */
class ImpersonationService
{
    /**
     * Marker ability stamped on every impersonation token so downstream code
     * (and audits) can distinguish an impersonated session from a normal login.
     * The token also carries the target user's real abilities so the
     * Platform_Admin experiences the tenant exactly as that user would.
     */
    public const IMPERSONATION_ABILITY = 'impersonation';

    /**
     * Short time-to-live for an impersonation token. Impersonation is a
     * sensitive, support-oriented action, so the scoped token is deliberately
     * short-lived and expires automatically (R8.6 "short-lived scoped token").
     */
    public const TOKEN_TTL_MINUTES = 30;

    /**
     * Audit event name recorded for an impersonation session (R34.3).
     */
    public const AUDIT_EVENT = 'impersonation';

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Begin an impersonation session: initialize the target tenant's database,
     * issue a short-lived scoped token bound to that tenant and target user,
     * and audit the session (R8.6, R34.3).
     *
     * @param  Tenant  $tenant  The target tenant whose database will be initialized.
     * @param  int|string  $targetUserId  The id of the user to impersonate inside that tenant.
     * @param  User|null  $actor  The Platform_Admin performing the impersonation (central user).
     * @param  string|null  $reason  Optional free-text reason recorded with the audit entry (R34.3).
     * @return array{
     *     token: string,
     *     expires_at: \Illuminate\Support\Carbon,
     *     tenant_id: int|string,
     *     target_user: array{id: int|string, name: string, email: string, roles: list<string>}
     * }
     *
     * @throws NotFoundHttpException When the target user does not exist in the tenant database.
     */
    public function impersonate(Tenant $tenant, int|string $targetUserId, ?User $actor = null, ?string $reason = null): array
    {
        $expiresAt = Carbon::now()->addMinutes(self::TOKEN_TTL_MINUTES);

        // Initialize the TARGET tenant's database for the duration of this
        // closure so the user lookup, token issuance, and audit all resolve
        // against — and are physically bound to — that tenant (R8.6).
        return $tenant->run(function () use ($targetUserId, $actor, $reason, $expiresAt, $tenant): array {
            /** @var User|null $target */
            $target = User::query()->find($targetUserId);

            if ($target === null) {
                throw new NotFoundHttpException(
                    "No user [{$targetUserId}] exists in the target tenant.",
                );
            }

            // A short-lived token carrying the impersonation marker plus the
            // target user's own abilities, so the session behaves exactly as
            // that user would while remaining identifiable as impersonation.
            $abilities = array_values(array_unique(array_merge(
                [self::IMPERSONATION_ABILITY],
                $target->abilities(),
            )));

            $newToken = $target->createToken(
                'impersonation:'.($actor?->getKey() ?? 'platform-admin'),
                $abilities,
                $expiresAt,
            );

            // Stamp the TARGET tenant onto the token (R1.6, R8.6). This closure
            // runs inside the target tenant's initialized database, so
            // `tenant('id')` is that tenant's id — the only tenant this token is
            // valid for. The ReconcileTenantUser guard compares this stamp on
            // every subsequent request, so an impersonation token can never be
            // replayed against another tenant's database. This mirrors how a
            // normal login stamps its issued token (see AuthService::login()).
            $tenantId = function_exists('tenant') ? tenant('id') : $tenant->getTenantKey();

            if ($tenantId !== null) {
                $newToken->accessToken->forceFill(['tenant_id' => $tenantId])->save();
            }

            $token = $newToken->plainTextToken;

            // Record the impersonation session in the TARGET tenant's audit log
            // with actor, target user, reason, and the session's expiry (R34.3).
            // The write is best-effort inside AuditRecorder; it must never block
            // the impersonation outcome.
            $this->audit->record(self::AUDIT_EVENT, $target, [
                'actor_type' => $actor ? $actor::class : null,
                'actor_id' => $actor?->getKey(),
                'actor_email' => $actor?->email,
                'tenant_id' => $tenant->getTenantKey(),
                'target_user_id' => $target->getKey(),
                'target_user_email' => $target->email,
                'reason' => $reason ?? 'Platform_Admin impersonation session',
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return [
                'token' => $token,
                'expires_at' => $expiresAt,
                'tenant_id' => $tenant->getTenantKey(),
                'target_user' => [
                    'id' => $target->getKey(),
                    'name' => $target->name,
                    'email' => $target->email,
                    'roles' => $target->getRoleNames()->values()->all(),
                ],
            ];
        });
    }
}
