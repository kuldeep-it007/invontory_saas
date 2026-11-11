<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Stock_Reservation model (R23.2, R23.4, R23.5).
 *
 * A reservation BLOCKS a quantity of a {@see Stock} row for an in-progress or
 * advance {@see Order} WITHOUT moving the owner's on-hand quantity (R23.4,
 * design "Stock blocking/reservation"). The reserved quantity is unavailable to
 * every other order while the reservation is {@see STATUS_ACTIVE}: available =
 * on-hand − Σ(active reservations). Cancelling or expiring the order releases
 * its reservations ({@see STATUS_RELEASED}), restoring available quantity to
 * its prior value (R23.5).
 *
 * This model is a plain record of a reservation; the availability arithmetic
 * and the reserve/release lifecycle live in
 * {@see \App\Services\StockReservationService} so the "available = on-hand −
 * active" invariant is computed in exactly one place.
 *
 * Quantities use DECIMAL(15,3) kept as strings via the `decimal` cast so they
 * are never silently coerced to float, preserving exactness for the
 * availability computation (R13.3).
 *
 * Tenant-owned and physically isolated (no `tenant_id`); {@see TenantAuditable}
 * records create/update/delete — including the release status change — in the
 * tenant `audit_logs` table (R34.1).
 *
 * @property int $id
 * @property int $stock_id
 * @property int|null $order_id
 * @property string $quantity
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class StockReservation extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * A reservation that BLOCKS stock: its quantity is subtracted from the
     * owner's available quantity (R23.4).
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * A reservation that no longer blocks stock: it has been released (order
     * cancelled or reservation expired), so its quantity is returned to
     * available (R23.5).
     */
    public const STATUS_RELEASED = 'released';

    /**
     * Mass-assignable attributes. The reserved row/order are assigned via the
     * {@see stock()} / {@see order()} relations or directly by id.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stock_id',
        'order_id',
        'quantity',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'stock_id' => 'integer',
        'order_id' => 'integer',
        'quantity' => 'decimal:3',
        'expires_at' => 'datetime',
    ];

    /**
     * The stock row this reservation blocks (R23.4).
     *
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * The order this reservation is held for (R23.2). Nullable — a reservation
     * can exist before its order row is finalised in edge flows.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Whether this reservation currently blocks stock (R23.4). An `active`
     * reservation subtracts from available; a `released` one does not (R23.5).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Scope to the reservations that currently block stock — the `active` ones
     * whose quantity feeds the availability computation (R23.4). Runs SQL-side
     * against the `(stock_id, status)` index so the available-quantity read
     * never materialises released history.
     *
     * @param  Builder<StockReservation>  $query
     * @return Builder<StockReservation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to the reservations that have lapsed: `active` reservations whose
     * `expires_at` is set and is at or before `$asOf` (default now). These are
     * the reservations the service releases so expired blocked stock returns to
     * available (R23.5).
     *
     * @param  Builder<StockReservation>  $query
     * @return Builder<StockReservation>
     */
    public function scopeExpired(Builder $query, ?\DateTimeInterface $asOf = null): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf ?? now());
    }
}
