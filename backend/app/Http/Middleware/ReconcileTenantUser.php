<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-authentication tenant / user reconciliation guard (R1.6, R1.3).
 *
 * Runs in the tenant pipeline AFTER Sanctum authentication and re-affirms the
 * invariant that the authenticated token actually belongs to the tenant whose
 * database is currently active. A user authenticated for tenant A must never
 * be able to act on a request that resolved to tenant B.
 *
 * ## How a mismatch can arise
 *
 * Tenant isolation is primarily PHYSICAL: Sanctum tokens and users live inside
 * each tenant's own database, and the tenancy middleware switches the default
 * connection to the resolved tenant's database BEFORE Sanctum runs. So in the
 * normal case a token minted for tenant A is simply absent from tenant B's
 * database and Sanctum cannot resolve it when the host resolves to tenant B —
 * the physical boundary already blocks the cross-tenant replay.
 *
 * This guard is DEFENCE IN DEPTH for the edge cases where that physical
 * guarantee could be weakened:
 *
 *   1. **Cloned / restored tenant databases.** If tenant B's database was
 *      cloned or restored from tenant A's (staging copies, backups, custom-
 *      domain cut-overs under R3.4), A's token rows ride along and could
 *      authenticate against B.
 *   2. **Token id + secret collision.** Sanctum tokens are `{id}|{secret}` and
 *      the id is a per-database auto-increment. If two tenant databases happen
 *      to hold a row with the same id AND the same hashed secret, a token
 *      minted for one validates against the other.
 *   3. **Future shared-store refactors.** If any later change authenticates
 *      Sanctum against a connection other than the active tenant, the physical
 *      guarantee no longer holds on its own.
 *
 * In every case the token's `tenant_id` stamp (recorded at login — see
 * {@see \App\Services\AuthService::login()}) still binds it to exactly one
 * tenant. This guard compares that stamp against the active tenant id and
 * rejects any mismatch with HTTP 403, surfaced through the centralized API
 * error envelope as `FORBIDDEN`.
 *
 * The guard is intentionally lenient about a MISSING stamp (null `tenant_id`):
 * legacy tokens issued before the stamp existed, or tokens created outside the
 * login path, are allowed through on the strength of the physical boundary.
 * Only a token stamped for a DIFFERENT tenant than the active one is rejected,
 * so the guard never breaks a correctly-scoped request.
 */
class ReconcileTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // No active tenant means this middleware is mounted on a pipeline that
        // did not initialize tenancy; there is nothing to reconcile.
        if (! function_exists('tenant') || tenant() === null) {
            return $next($request);
        }

        $user = $request->user();

        // Unauthenticated requests are handled by Sanctum earlier in the
        // pipeline; nothing to reconcile here.
        if ($user === null) {
            return $next($request);
        }

        $activeTenantId = tenant('id');

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $stampedTenantId = $token->getAttribute('tenant_id');

            // A token stamped for a DIFFERENT tenant than the one currently
            // active is a cross-tenant replay — reject it (R1.6). A null stamp
            // (legacy / non-login token) relies on the physical boundary and is
            // allowed through.
            if ($stampedTenantId !== null && (string) $stampedTenantId !== (string) $activeTenantId) {
                abort(403, 'The authenticated user does not belong to the resolved tenant.');
            }
        }

        return $next($request);
    }
}
