<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central-database PlanLimit model.
 *
 * A flexible key/value usage quota attached to a {@see Plan} (R4.2, R4.3). A
 * value of 0 or NULL means unlimited (R4.4). Lives in the central database.
 */
class PlanLimit extends Model
{
    protected $connection = 'central';

    /**
     * Supported quota keys (R4.3). Exposed as constants so callers (QuotaService,
     * seeders, provisioning) reference a single source of truth instead of raw
     * strings. The authoritative allow-list is {@see self::QUOTA_KEYS}.
     */
    public const MAX_DISTRIBUTORS = 'max_distributors';

    public const MAX_DEALERS = 'max_dealers';

    public const MAX_DEALERS_PER_DISTRIBUTOR = 'max_dealers_per_distributor';

    public const MAX_PRODUCTS = 'max_products';

    public const MAX_STOCK_POINTS = 'max_stock_points';

    public const MAX_USERS = 'max_users';

    public const MAX_ORDERS_PER_MONTH = 'max_orders_per_month';

    public const MAX_STORAGE_MB = 'max_storage_mb';

    /**
     * The documented allow-list of quota keys the platform supports (R4.3).
     * Any key outside this list is not a recognised quota.
     *
     * @var list<string>
     */
    public const QUOTA_KEYS = [
        self::MAX_DISTRIBUTORS,
        self::MAX_DEALERS,
        self::MAX_DEALERS_PER_DISTRIBUTOR,
        self::MAX_PRODUCTS,
        self::MAX_STOCK_POINTS,
        self::MAX_USERS,
        self::MAX_ORDERS_PER_MONTH,
        self::MAX_STORAGE_MB,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    /**
     * Whether a quota key is a recognised, supported quota (R4.3).
     */
    public static function isSupportedKey(string $key): bool
    {
        return in_array($key, self::QUOTA_KEYS, true);
    }

    /**
     * A quota of 0 or NULL means UNLIMITED (R4.4). Enforcement code compares
     * counts against limits only when a quota is NOT unlimited.
     */
    public static function isUnlimitedValue(?int $value): bool
    {
        return $value === null || $value === 0;
    }

    /**
     * Whether THIS limit row represents an unlimited quota (R4.4).
     */
    public function isUnlimited(): bool
    {
        return self::isUnlimitedValue($this->value);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
