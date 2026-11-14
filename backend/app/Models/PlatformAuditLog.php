<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableAuditException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Append-only central audit record for Platform_Admin actions.
 *
 * Lives in the central database (tenant audit logs live in each tenant DB and
 * are never touched). Rows cannot be updated or deleted through the model.
 */
class PlatformAuditLog extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableAuditException::forUpdate();
        });
        static::deleting(function (): void {
            throw ImmutableAuditException::forDelete();
        });
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(
        string $event,
        ?Request $request = null,
        ?string $tenantId = null,
        array $old = [],
        array $new = [],
        ?string $reason = null,
    ): self {
        $request ??= request();
        $actor = $request?->user();

        return self::create([
            'actor_id' => $actor?->getKey(),
            'actor_email' => $actor?->email,
            'event' => $event,
            'tenant_id' => $tenantId,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
