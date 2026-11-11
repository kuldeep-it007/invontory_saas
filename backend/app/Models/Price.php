<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Price model.
 *
 * A configured Price defined at either the Product or the Collection level
 * (R12.1). A Product-level price overrides the Collection-level price for that
 * product when both are present (R12.4). A markup or MUP configured per
 * Collection is carried on the Collection-level price row (R12.2).
 *
 * Amounts are in the Tenant's single configured currency (R39.1). Tenant-owned
 * and auditable (R34.1); resolved against the active tenant database (no
 * `tenant_id` column).
 */
class Price extends Model implements Auditable
{
    use TenantAuditable;

    /** Price defined at the Product level (R12.1). */
    public const LEVEL_PRODUCT = 'product';

    /** Price defined at the Collection level (R12.1). */
    public const LEVEL_COLLECTION = 'collection';

    /** The two valid price levels (R12.1). */
    public const LEVELS = [self::LEVEL_PRODUCT, self::LEVEL_COLLECTION];

    /** Flat markup added as a margin on top of the base (R12.2). */
    public const MARKUP_TYPE_MARKUP = 'markup';

    /** MUP — markup on price (R12.2). */
    public const MARKUP_TYPE_MUP = 'mup';

    /** The valid markup types; NULL means no markup (R12.2). */
    public const MARKUP_TYPES = [self::MARKUP_TYPE_MARKUP, self::MARKUP_TYPE_MUP];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'level',
        'ref_id',
        'amount',
        'markup_type',
        'markup_value',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'ref_id' => 'integer',
        'amount' => 'decimal:2',
        'markup_value' => 'decimal:4',
    ];

    /**
     * Whether a value is one of the two valid price levels (R12.1).
     */
    public static function isValidLevel(string $level): bool
    {
        return in_array($level, self::LEVELS, true);
    }

    /**
     * Whether a value is a valid markup type (R12.2).
     */
    public static function isValidMarkupType(?string $markupType): bool
    {
        return $markupType === null || in_array($markupType, self::MARKUP_TYPES, true);
    }
}
