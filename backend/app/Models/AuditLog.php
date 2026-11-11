<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableAuditException;
use App\Models\Concerns\TenantAuditable;
use App\Support\AuditRecorder;
use OwenIt\Auditing\Models\Audit as OwenItAudit;

/**
 * Append-only audit log entry (tenant database).
 *
 * This is the single, canonical audit model for the platform. It is wired two
 * ways against one coherent mechanism (see `config/audit.php`
 * `implementation` => this class):
 *
 *   1. **Model mutations** — owen-it/laravel-auditing records create/update/
 *      delete of tenant-owned models (those using {@see TenantAuditable})
 *      by writing a row through this model (R34.1). It extends owen-it's
 *      {@see OwenItAudit} so it fully satisfies the auditing driver contract.
 *   2. **Security events** — {@see AuditRecorder} writes login,
 *      logout, password-reset, and OTP-verify events through this same model
 *      and table (R7.6). owen-it does not observe these (they are not Eloquent
 *      model mutations), so the recorder is their canonical path.
 *
 * Both paths persist to the per-tenant `audit_logs` table on the active tenant
 * connection, so there is exactly one audit trail per tenant.
 *
 * **Append-only (R34.2).** Entries are immutable once written. Updating an
 * existing entry or deleting any entry is rejected by throwing
 * {@see ImmutableAuditException} from the model's persistence hooks, so the
 * stored entry can never be altered or removed through Eloquent. The initial
 * insert performed by owen-it / the recorder is still permitted.
 */
class AuditLog extends OwenItAudit
{
    protected $table = 'audit_logs';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Register append-only guards.
     *
     * - `updating` fires on every save of an already-persisted entry → reject.
     * - `deleting` fires on every delete attempt → reject.
     *
     * The `creating` event is intentionally left untouched so the first insert
     * (by owen-it or the AuditRecorder) succeeds; after that the record exists
     * and any further `save()` routes through `updating` and is blocked.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableAuditException::forUpdate();
        });

        static::deleting(function (): void {
            throw ImmutableAuditException::forDelete();
        });
    }
}
