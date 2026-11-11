<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableLedgerException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tenant-database Stock_Ledger model.
 *
 * The immutable, append-only record of every stock movement (R15.1). Exactly
 * one row is written per movement by {@see App\Services\StockMovementService}
 * inside the same transaction that changes the `stock` balance, capturing the
 * signed movement `quantity`, the `balance_after` (the owner's post-movement
 * `stock.quantity` for that product/stock_point — the balance-after invariant,
 * R15.3), the `movement_type`, `reason`, acting `actor_id`, `reference`, and
 * timestamp.
 *
 * **Append-only (R15.2).** Entries are immutable once written: updating an
 * existing entry or deleting any entry is rejected by throwing
 * {@see ImmutableLedgerException} from the model's persistence hooks, so a
 * stored movement can never be altered or removed through Eloquent. The initial
 * insert that appends a new entry is still permitted (the `creating` event is
 * intentionally left untouched).
 *
 * Quantities use DECIMAL(15,3) kept as strings via the `decimal` cast so they
 * are never silently coerced to float, preserving exactness for the
 * balance-after invariant.
 *
 * Tenant-owned and physically isolated — there is no `tenant_id` column; the
 * row already lives in the active tenant's database. The ledger is intentionally
 * NOT {@see App\Models\Concerns\TenantAuditable}: it IS the movement audit trail
 * for stock, so auditing it through the generic audit log would be redundant.
 *
 * @property string $stockable_type
 * @property int $stockable_id
 * @property int $product_id
 * @property int $stock_point_id
 * @property string $movement_type
 * @property string $quantity
 * @property string $balance_after
 * @property string|null $reason
 * @property int|null $actor_id
 * @property string|null $reference
 */
class StockLedger extends Model
{
    /**
     * Explicit table name: the ledger table is the singular `stock_ledger`
     * (not Laravel's pluralized default `stock_ledgers`).
     *
     * @var string
     */
    protected $table = 'stock_ledger';

    /**
     * Mass-assignable attributes. The polymorphic owner may be assigned through
     * the {@see owner()} relation or directly via the morph columns.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'product_id',
        'stock_point_id',
        'movement_type',
        'quantity',
        'balance_after',
        'reason',
        'actor_id',
        'reference',
    ];

    protected $casts = [
        'stockable_id' => 'integer',
        'product_id' => 'integer',
        'stock_point_id' => 'integer',
        'quantity' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'actor_id' => 'integer',
    ];

    /**
     * Register append-only guards (R15.2).
     *
     * - `updating` fires on every save of an already-persisted entry → reject.
     * - `deleting` fires on every delete attempt → reject.
     *
     * The `creating` event is intentionally left untouched so the first insert
     * (appending a movement) succeeds; after that the row exists and any further
     * `save()` routes through `updating` and is blocked.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableLedgerException::forUpdate('stock ledger');
        });

        static::deleting(function (): void {
            throw ImmutableLedgerException::forDelete('stock ledger');
        });
    }

    /**
     * The polymorphic owner whose stock moved: a {@see Company}, a
     * {@see Distributor}, or (when enabled) a Dealer (R13.4, R13.5, R13.7).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('stockable');
    }

    /**
     * The stock point at which the movement occurred (R15.1).
     *
     * @return BelongsTo<StockPoint, $this>
     */
    public function stockPoint(): BelongsTo
    {
        return $this->belongsTo(StockPoint::class);
    }
}
