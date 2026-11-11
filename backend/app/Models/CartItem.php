<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Cart_Item model (R20.3).
 *
 * A single Product-and-quantity line within a {@see Cart}, carrying the full
 * resolved price breakdown snapshot (RRP, discount %, discounted unit price,
 * net, tax, total) so the cart renders per-line figures without recomputing on
 * read. The snapshot is refreshed whenever the quantity changes.
 *
 * Tenant-owned and physically isolated (no `tenant_id`); auditable (R34.1).
 *
 * @property int $cart_id
 * @property int $product_id
 * @property string $quantity
 * @property string $rrp
 * @property string $discount_pct
 * @property string $unit_price
 * @property string $net_amount
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $total_amount
 */
class CartItem extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'allocations',
        'rrp',
        'discount_pct',
        'unit_price',
        'net_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
    ];

    protected $casts = [
        'cart_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:3',
        'allocations' => 'array',
        'rrp' => 'decimal:2',
        'discount_pct' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * The owning cart.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The ordered product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
