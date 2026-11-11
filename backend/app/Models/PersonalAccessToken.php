<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Tenant-scoped Sanctum personal access token.
 *
 * Tokens live in each tenant's own database (physical isolation, R1.1); the
 * active tenant connection resolves them on every authenticated request. This
 * model extends Sanctum's base token with a {@see $tenant_id} stamp recorded
 * at creation time so the {@see \App\Http\Middleware\ReconcileTenantUser}
 * guard can re-affirm, AFTER Sanctum authenticates, that the presented token
 * was actually issued for the tenant whose database is currently active
 * (R1.6, R1.3).
 *
 * ## Why stamp the tenant id?
 *
 * Isolation is primarily physical: a token minted in tenant A's database is
 * simply absent from tenant B's database, so Sanctum cannot resolve it when a
 * request is identified to tenant B. The stamp is DEFENCE IN DEPTH for the
 * edge cases where that physical guarantee could be weakened — e.g. a tenant
 * database restored / cloned from another (carrying the other tenant's token
 * rows), an auto-increment token-id + secret collision across databases, or a
 * future refactor that authenticates against a shared token store. In all of
 * those the stamp still binds the token to exactly one tenant and the guard
 * rejects a cross-tenant replay with HTTP 403.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The `tenant_id` stamp is mass-assignable so it can be set when the token
     * is created at login (R1.6).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'tenant_id',
    ];
}
