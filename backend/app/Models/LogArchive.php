<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableAuditException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One archived log file in the master bucket (central database).
 *
 * Append-only: rows cannot be edited, and can only be removed by the retention
 * job once the file is past the retention period (at least 365 days).
 *
 * @property int $id
 * @property string $scope
 * @property string|null $tenant_id
 * @property string|null $tenant_slug
 * @property string $kind
 * @property Carbon $log_date
 * @property string $path
 * @property int $size
 * @property string $sha256
 * @property int $lines
 */
class LogArchive extends Model
{
    public const SCOPE_TENANT = 'tenant';

    public const SCOPE_PLATFORM = 'platform';

    public const KIND_APP = 'app';

    public const KIND_AUDIT = 'audit';

    protected $connection = 'central';

    public $timestamps = false;

    protected $guarded = [];

    /** Set only by the retention job, for rows past the retention period. */
    public static bool $allowRetentionDelete = false;

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'size' => 'integer',
            'lines' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableAuditException::forUpdate();
        });

        static::deleting(function (): void {
            if (! static::$allowRetentionDelete) {
                throw ImmutableAuditException::forDelete();
            }
        });
    }
}
