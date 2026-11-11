<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableLedgerException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tenant-database Transaction_Ledger model.
 *
 * The immutable, append-only running account-balance record per Buyer (R25.1).
 * Exactly one row is appended by {@see App\Services\AccountLedgerService}
 * whenever a confirmed purchase or a recorded Payment affects a Buyer's
 * account, capturing the signed `amount` and the `balance_after` (previous
 * balance + signed amount — the transaction-ledger balance invariant, R24.2,
 * R25.1). A Buyer's Outstanding_Balance is the latest `balance_after` for that
 * Buyer (R25.2).
 *
 * **Append-only (R25.3).** Entries are immutable once written: updating an
 * existing entry or deleting any entry is rejected by throwing
 * {@see ImmutableLedgerException} from the model's persistence hooks — exactly
 * mirroring {@see StockLedger}. The initial insert that appends a new entry is
 * still permitted (the `creating` event is intentionally left untouched).
 *
 * Monetary values use DECIMAL(15,2) kept as strings via the `decimal` cast so
 * they are never silently coerced to float, preserving exactness for the
 * balance-after invariant.
 *
 * Tenant-owned and physically isolated — there is no `tenant_id` column; the
 * row already lives in the active tenant's database. Like {@see StockLedger},
 * the transaction ledger is intentionally NOT TenantAuditable: it IS the
 * account movement trail, so auditing it through the generic audit log would be
 * redundant.
 *
 * @property string $buyer_type
 * @property int $buyer_id
 * @property string $entry_type
 * @property string $amount
 * @property string $balance_after
 * @property int|null $payment_id
 * @property string|null $reference
 * @property int|null $actor_id
 */
class TransactionLedger extends Model
{
    /**
     * Entry types written to the transaction ledger.
     */
    public const ENTRY_PURCHASE = 'purchase';

    public const ENTRY_PAYMENT = 'payment';

    public const ENTRY_RETURN = 'return';

    public const ENTRY_CANCELLATION = 'cancellation';

    /**
     * Explicit table name: the ledger table is the singular `transaction_ledger`
     * (not Laravel's pluralized default `transaction_ledgers`).
     *
     * @var string
     */
    protected $table = 'transaction_ledger';

    /**
     * Mass-assignable attributes. The polymorphic Buyer may be assigned through
     * the {@see buyer()} relation or directly via the morph columns.
     *
     * @var list<string>
     */
    protected $fillable = [
        'buyer_type',
        'buyer_id',
        'entry_type',
        'amount',
        'balance_after',
        'payment_id',
        'reference',
        'actor_id',
    ];

    protected $casts = [
        'buyer_id' => 'integer',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payment_id' => 'integer',
        'actor_id' => 'integer',
    ];

    /**
     * Register append-only guards (R25.3).
     *
     * - `updating` fires on every save of an already-persisted entry → reject.
     * - `deleting` fires on every delete attempt → reject.
     *
     * The `creating` event is intentionally left untouched so the first insert
     * (appending an entry) succeeds; after that the row exists and any further
     * `save()` routes through `updating` and is blocked.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableLedgerException::forUpdate('transaction ledger');
        });

        static::deleting(function (): void {
            throw ImmutableLedgerException::forDelete('transaction ledger');
        });
    }

    /**
     * The polymorphic Buyer whose account moved: a {@see Distributor} or a
     * {@see Dealer} (R25.1).
     */
    public function buyer(): MorphTo
    {
        return $this->morphTo('buyer');
    }

    /**
     * The Payment that produced this entry, when the entry is a payment (R24.2).
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
