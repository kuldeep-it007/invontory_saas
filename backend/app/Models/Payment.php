<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Payment model.
 *
 * A manually recorded Payment applied against a Buyer's outstanding balance
 * (R24.1). Captures the `amount`, `method`, `reference`, payment date
 * (`paid_at`) and `recorded_by` acting-user identity (R24.1). Recording a
 * payment also appends a `transaction_ledger` entry, updates the running
 * balance (R24.2), may be allocated across one or more invoices via
 * {@see PaymentAllocation} (R24.3), and queues a payment-recorded email
 * (R24.4) — all orchestrated by {@see App\Services\PaymentService}.
 *
 * The Buyer is polymorphic: a {@see Distributor} or a {@see Dealer}. Because
 * isolation is physical (one database per tenant) there is no `tenant_id`
 * column.
 *
 * This is a tenant-owned model, so {@see TenantAuditable} records its
 * create/update/delete in the tenant `audit_logs` table (R34.1).
 *
 * @property string $buyer_type
 * @property int $buyer_id
 * @property string $amount
 * @property string $method
 * @property string|null $reference
 * @property Carbon $paid_at
 * @property int|null $recorded_by
 */
class Payment extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * Supported payment methods (R24.1). Stored as a string so new methods need
     * no schema change; this list documents the recognised values.
     *
     * @var list<string>
     */
    public const METHODS = ['cash', 'cheque', 'bank_transfer', 'upi', 'card', 'other'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_type',
        'buyer_id',
        'amount',
        'method',
        'reference',
        'paid_at',
        'recorded_by',
    ];

    protected $casts = [
        'buyer_id' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'recorded_by' => 'integer',
    ];

    /**
     * The polymorphic Buyer this payment is recorded against: a
     * {@see Distributor} or a {@see Dealer} (R24.1).
     */
    public function buyer(): MorphTo
    {
        return $this->morphTo('buyer');
    }

    /**
     * The allocations splitting this payment across one or more invoices
     * (R24.3).
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * The transaction-ledger entry appended when this payment was recorded
     * (R24.2).
     *
     * @return BelongsTo<TransactionLedger, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(TransactionLedger::class, 'id', 'payment_id');
    }
}
