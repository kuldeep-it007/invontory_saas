<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-database PaymentAllocation model.
 *
 * One slice of a {@see Payment} applied to a single invoice (R24.3). A payment
 * may be split across several invoices (partial payments and multi-invoice
 * allocation); the sum of a payment's allocation amounts never exceeds the
 * payment amount — that ceiling is enforced in
 * {@see App\Services\PaymentService} (R24.3).
 *
 * The `invoice_id` references the `invoices` table (task 9.8). Because isolation
 * is physical (one database per tenant) there is no `tenant_id` column.
 *
 * @property int $payment_id
 * @property int $invoice_id
 * @property string $amount
 */
class PaymentAllocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'invoice_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    /**
     * The Payment this allocation belongs to (R24.3).
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
