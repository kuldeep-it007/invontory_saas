<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Discount model.
 *
 * A configured discount of one of exactly two supported types — `customer` or
 * `online` (R12.3). Any other type is rejected: the database enum, this model's
 * setter guard, and request validation all enforce the same allow-list. Where
 * several applicable discounts coexist for a Buyer/Order_Line they are applied
 * cumulatively by the price resolver (R12.5).
 *
 * Tenant-owned and auditable (R34.1); resolved against the active tenant
 * database (no `tenant_id` column).
 */
class Discount extends Model implements Auditable
{
    use TenantAuditable;

    /** Discount tied to a specific customer/Buyer (R12.3). */
    public const TYPE_CUSTOMER = 'customer';

    /** Online storefront discount (R12.3). */
    public const TYPE_ONLINE = 'online';

    /** The ONLY two valid discount types (R12.3). */
    public const TYPES = [self::TYPE_CUSTOMER, self::TYPE_ONLINE];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'customer_type',
        'percentage',
        'buyer_type',
        'buyer_id',
        'category_id',
        'collection_id',
        'colour_id',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'percentage' => 'decimal:4',
        'is_active' => 'boolean',
        'category_id' => 'integer',
        'collection_id' => 'integer',
        'colour_id' => 'integer',
    ];

    /**
     * The specificity score of this discount: more non-null scope dimensions
     * (buyer, then colour/collection/category) rank higher so the resolver can
     * prefer the most specific match, Rosetta-style (R12.5). Buyer scope is the
     * strongest signal, then the deepest catalog level.
     */
    public function specificity(): int
    {
        $score = 0;
        if ($this->buyer_id !== null) {
            $score += 8;
        }
        if ($this->colour_id !== null) {
            $score += 4;
        }
        if ($this->collection_id !== null) {
            $score += 2;
        }
        if ($this->category_id !== null) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Whether a value is one of the two valid discount types (R12.3).
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * Reject any discount type other than `customer`/`online` at the model
     * layer (R12.3), so an invalid type can never be persisted even when the
     * attribute is set directly rather than through request validation.
     *
     * @throws InvalidArgumentException when the type is not a supported value.
     */
    public function setTypeAttribute(string $type): void
    {
        if (! self::isValidType($type)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid discount type "%s"; only [%s] are supported.',
                $type,
                implode(', ', self::TYPES),
            ));
        }

        $this->attributes['type'] = $type;
    }

    /**
     * The optional Buyer (Distributor or Dealer) this discount is scoped to; a
     * NULL buyer means broadly applicable (R12.4).
     *
     * @return MorphTo<Model, $this>
     */
    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }
}
