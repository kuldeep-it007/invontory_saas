<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Order_Line model.
 *
 * A single Product-and-quantity entry within an {@see Order} (R20.3, R20.4).
 * Submitting a cart of N entries creates exactly one Order with one Order_Line
 * per entry (R20.4). Each line records the ordered `product_id`, the
 * `quantity`, and the resolved per-unit `unit_price` and `discount` in the
 * tenant's single currency (R12.4, R12.5, R39.1).
 *
 * Quantity uses DECIMAL(15,3) kept as a string via the `decimal` cast so it is
 * never silently coerced to float, preserving exactness as the quantity flows
 * from cart to order to the confirmation stock movement (R13.3). Monetary
 * values use DECIMAL(15,2).
 *
 * Tenant-owned and physically isolated (no `tenant_id`); {@see TenantAuditable}
 * records create/update/delete in the tenant `audit_logs` table (R34.1).
 *
 * @property int $order_id
 * @property int $product_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $discount
 */
class OrderLine extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    /**
     * The owning Order (R20.4).
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The ordered Product (R20.3).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The per-stock-point source allocations for this line (R16, R20). Empty
     * when the line is fulfilled from the seller's primary stock point; one row
     * per source point when the buyer split the line across several points.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<OrderLineAllocation, $this>
     */
    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderLineAllocation::class);
    }
}
