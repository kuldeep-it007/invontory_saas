<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Services\StockMovementService;
use App\Services\StockReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Stock model.
 *
 * The per-(owner, product, stock_point) on-hand quantity — the Platform's
 * single source of truth for inventory (R13.1, R13.3). The owner is
 * polymorphic (`stockable`): a {@see Company} or {@see Distributor}, or a
 * Dealer only when dealer stock-holding is enabled for the tenant (R13.4,
 * R13.5). When dealer stock-holding is disabled, Dealers are pure consumers
 * and hold no Stock, so no stock rows are created with a Dealer owner (R13.6,
 * R13.7); that rule is enforced at the application layer (there is no DB-level
 * owner-type constraint because the physical model is polymorphic).
 *
 * Quantities use DECIMAL(15,3) and are kept as strings by Eloquent's `decimal`
 * cast so they are never silently coerced to float, preserving exactness for
 * the ledger balance-after invariant (R13.3). This model records the current
 * balance only; every change flows through the StockMovementService and writes
 * an immutable `stock_ledger` entry (introduced in task 7.12).
 *
 * Tenant-owned and physically isolated (no `tenant_id`); {@see TenantAuditable}
 * records create/update/delete in the tenant `audit_logs` table (R34.1).
 *
 * @property string $quantity
 * @property string|null $low_stock_threshold
 */
class Stock extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * Explicit table name: the inventory balance table is the singular `stock`
     * (not Laravel's pluralized default `stocks`).
     *
     * @var string
     */
    protected $table = 'stock';

    /**
     * Mass-assignable attributes. The polymorphic owner is assigned through the
     * {@see owner()} relation rather than mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'stock_point_id',
        'quantity',
        'low_stock_threshold',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
    ];

    /**
     * The polymorphic owner that holds this stock: a Company, Distributor, or
     * (when enabled) Dealer (R13.4, R13.5, R13.7).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('stockable');
    }

    /**
     * The product this stock quantity is held for (R13.3). Eager-loaded by the
     * low-stock report so each breaching row carries its product context for
     * rendering/export (R19.3) without an N+1.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The stock point holding this quantity (R13.3).
     *
     * @return BelongsTo<StockPoint, $this>
     */
    public function stockPoint(): BelongsTo
    {
        return $this->belongsTo(StockPoint::class);
    }

    /**
     * The reservations held against this stock row (R23.4). The ACTIVE ones
     * reduce the available quantity (available = on-hand − Σ active
     * reservations) without moving the on-hand `quantity`; released ones no
     * longer block stock (R23.5). The availability arithmetic lives in
     * {@see StockReservationService}.
     *
     * @return HasMany<StockReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Scope to the stock rows that are breaching their configured low-stock
     * threshold (R19.2, R19.3): a threshold is set (non-null — a row with no
     * threshold can never breach, R19.1) AND the on-hand quantity is at or
     * below that threshold. The `<=` comparison runs SQL-side so the low-stock
     * report never loads non-breaching rows into memory. Comparing the two
     * DECIMAL(15,3) columns against each other keeps the test exact for
     * fractional UOM quantities (R13.3).
     *
     * @param  Builder<Stock>  $query
     * @return Builder<Stock>
     */
    public function scopeBreachingLowStock(Builder $query): Builder
    {
        return $query
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('quantity', '<=', 'low_stock_threshold');
    }

    /**
     * Scope to the stock rows owned by a single polymorphic owner (R13.4,
     * R28.10). Used to confine a buyer-side principal (e.g. a Distributor) to
     * its OWN stock — i.e. only the rows held at a stock point it owns — rather
     * than the whole tenant's inventory. Company-side roles are tenant-wide and
     * do not apply this scope.
     *
     * The predicate matches the polymorphic owner columns directly
     * (`stockable_type` + `stockable_id`), the same owner-filter the reporting
     * engine uses, so visibility stays consistent across read surfaces.
     *
     * @param  Builder<Stock>  $query
     * @return Builder<Stock>
     */
    public function scopeOwnedBy(Builder $query, string $ownerType, int $ownerId): Builder
    {
        return $query
            ->where('stockable_type', $ownerType)
            ->where('stockable_id', $ownerId);
    }

    /**
     * Whether THIS stock row is currently at or below its configured low-stock
     * threshold (R19.2). A row with no threshold is never low (R19.1). The
     * comparison is done on the fixed-scale decimal strings so it is exact and
     * never coerced to float.
     */
    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }

        return $this->compareQuantities((string) $this->quantity, (string) $this->low_stock_threshold) <= 0;
    }

    /**
     * Compare two DECIMAL(15,3) quantity strings exactly, returning -1/0/1.
     * Uses bcmath when available and otherwise scaled-integer arithmetic, so
     * the comparison never silently coerces to float (mirrors the exactness
     * guarantee of {@see StockMovementService}).
     */
    private function compareQuantities(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 3);
        }

        return $this->toScaledInt($a) <=> $this->toScaledInt($b);
    }

    /**
     * Convert a decimal string to an integer in the smallest unit (×10^3) for
     * exact integer comparison on the bcmath-less fallback path.
     */
    private function toScaledInt(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = substr(str_pad($parts[1] ?? '', 3, '0'), 0, 3);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    }
}
