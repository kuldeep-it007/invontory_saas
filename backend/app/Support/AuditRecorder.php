<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Concerns\TenantAuditable;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

/**
 * Canonical audit writer for security-relevant events (R7.2, R7.6).
 *
 * Writes a single row for auth security events — login, failed login, logout,
 * password reset, and OTP verification — against the active tenant database.
 * These are not Eloquent model mutations, so owen-it/laravel-auditing does not
 * observe them; this recorder is their canonical path. Model create/update/
 * delete audits are instead produced by owen-it through the
 * {@see TenantAuditable} trait. Both paths write through
 * the same append-only {@see AuditLog} model
 * and the same per-tenant `audit_logs` table, so there is one coherent audit
 * trail per tenant (R34.1, R34.2).
 *
 * The write is best-effort: a failure to record the audit entry must never
 * block or alter the primary auth outcome, so exceptions are swallowed.
 */
class AuditRecorder
{
    /**
     * Record a security event in the audit log.
     *
     * @param  string  $event  Event name, e.g. `login`, `login_failed`, `logout`, `password_reset_requested`.
     * @param  User|null  $user  The acting user when known (null for failed logins with an unknown identity).
     * @param  array<string, mixed>  $context  Extra non-sensitive context stored under `new_values` (never the password).
     * @param  string|null  $reason  The reason for the action, stored in the dedicated `reason` column. Required for
     *                               the events R34.3 enumerates — limit overrides, impersonation, plan changes, and
     *                               stock adjustments — so the audit trail captures both the action and its reason.
     */
    public function record(string $event, ?User $user = null, array $context = [], ?Request $request = null, ?string $reason = null): void
    {
        $request ??= request();

        try {
            AuditLog::create([
                'user_type' => $user ? $user::class : null,
                'user_id' => $user?->getKey(),
                'event' => $event,
                // Self-referential auditable for standalone security events so the
                // NOT NULL morph columns are satisfied; task 1.10 may refine this.
                'auditable_type' => $user ? $user::class : User::class,
                'auditable_id' => $user?->getKey() ?? 0,
                'old_values' => null,
                'new_values' => $context ?: null,
                'reason' => $reason,
                'url' => $request?->fullUrl(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable $e) {
            // Best-effort: never let an audit failure affect the auth response.
        }
    }
}
