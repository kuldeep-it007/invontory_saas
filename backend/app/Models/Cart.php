<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Cart model (R20.3).
 *
 * A durable shopping cart owned by a polymorphic Buyer (Distributor or Dealer —
 * R20.1, R20.2) that accumulates line items before submission into exactly one
 * Order (R20.4). Exactly one `open` cart exists per buyer at a time.
 *
 * Tenant-owned and physically isolated (no `tenant_id`); auditable (R34.1).
 *
 * @property int $id
 * @property string|null $buyer_type
 * @property int|null $buyer_id
 * @property string $status
 * @property int|null $order_id
 */
class Cart extends Model implements Auditable
{
    use TenantAuditable;

    /** The cart is being built (R20.3). */
    public const STATUS_OPEN = 'open';

    /** The cart has been turned into an order (R20.4). */
    public const STATUS_SUBMITTED = 'submitted';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_type',
        'buyer_id',
        'status',
        'order_id',
    ];

    protected $casts = [
        'buyer_id' => 'integer',
        'order_id' => 'integer',
    ];

    /**
     * The Buyer (Distributor or Dealer) that owns this cart (R20.1, R20.2).
     */
    public function buyer(): MorphTo
    {
        return $this->morphTo('buyer');
    }

    /**
     * The cart's line items.
     *
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
