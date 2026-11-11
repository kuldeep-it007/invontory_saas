<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Order_Line_Allocation model (R16, R20).
 *
 * Records that a portion of an {@see OrderLine}'s quantity is sourced from a
 * specific seller {@see StockPoint}. Present only when the buyer split a line
 * across multiple stock points; a line with no allocations is fulfilled from
 * the seller's primary point.
 *
 * @property int $order_line_id
 * @property int $stock_point_id
 * @property string $quantity
 */
class OrderLineAllocation extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_line_id',
        'stock_point_id',
        'quantity',
    ];

    protected $casts = [
        'order_line_id' => 'integer',
        'stock_point_id' => 'integer',
        'quantity' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    /**
     * @return BelongsTo<StockPoint, $this>
     */
    public function stockPoint(): BelongsTo
    {
        return $this->belongsTo(StockPoint::class);
    }
}
