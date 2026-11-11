<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates exactly one {@see Invoice} per confirmed Order using the SELLER's
 * own invoice series, with strictly-increasing numbers within each series
 * (R21.5, design "Confirmation" / "Payments & Accounts").
 *
 * ## Where this runs
 *
 * {@see generate()} is called SYNCHRONOUSLY by
 * {@see OrderConfirmationService} (task 9.7) from inside the single
 * confirmation `DB::transaction` — after the stock movements are applied via
 * {@see StockMovementService} (FOR UPDATE, all-or-nothing) and before the
 * confirmation response returns. Because it participates in that transaction,
 * an invoice-generation failure rolls back the whole confirmation (no stock,
 * ledger, or invoice change persists — R35.2, R35.3), and a successful
 * confirmation always leaves exactly one invoice behind.
 *
 * ## Strictly-increasing per-series numbers under concurrency
 *
 * Each seller runs one or more named series; the per-series `number` is
 * allocated as `MAX(number for this series) + 1`. To keep that allocation safe
 * when two confirmations for the same series race (the "No overselling"-style
 * contention of the confirmation path), the MAX read is taken with a
 * `SELECT ... FOR UPDATE` lock on the existing rows of that series. Combined
 * with the UNIQUE `(series, number)` index, this serializes allocation so two
 * concurrent confirmations can never be assigned the same number, and numbers
 * within a series are strictly increasing (never reused, never regressed).
 *
 * This service is deliberately thin: it allocates the next number and writes
 * one row. It performs NO transaction management of its own — it relies on the
 * caller's confirmation transaction so the lock is held for the right scope.
 */
class InvoiceService
{
    /**
     * Generate the single invoice for a just-confirmed order.
     *
     * MUST be invoked inside the order-confirmation transaction so the
     * `FOR UPDATE` series lock is held until commit (R21.5). The caller passes
     * the order's identity + total and the SELLER model whose series is used.
     *
     * @param  int  $orderId  The confirmed order's id (one invoice per order).
     * @param  Model  $seller  The Company or Distributor whose series is used
     *                         (the order's seller); its morph type/key is stored on the invoice.
     * @param  string  $series  The seller's invoice series identifier. Numbers
     *                          are strictly increasing within THIS series (R21.5).
     * @param  string  $amount  The invoice total (the confirmed order total), as a
     *                          DECIMAL(15,2) string in the tenant's single currency (R39.1).
     * @return Invoice The persisted invoice with its allocated series number.
     */
    public function generate(int $orderId, Model $seller, string $series, string $amount): Invoice
    {
        $sellerType = $seller->getMorphClass();
        $sellerId = (int) $seller->getKey();

        // Allocate the next number WITHIN this series. Lock the existing rows of
        // the series FOR UPDATE so concurrent confirmations for the same series
        // serialize here and cannot both read the same MAX (R21.5). The lock is
        // held until the caller's confirmation transaction commits.
        $currentMax = Invoice::query()
            ->where('series', $series)
            ->lockForUpdate()
            ->max('number');

        $nextNumber = ((int) $currentMax) + 1;

        // Persist the single invoice. The seller morph columns are not
        // mass-assignable, so they are set via the seller() relation association
        // before save; the UNIQUE (series, number) index is the final guard
        // against any duplicate slipping through (R21.5, Property 11).
        $invoice = new Invoice([
            'order_id' => $orderId,
            'series' => $series,
            'number' => $nextNumber,
            'amount' => $amount,
            'overdue' => false,
        ]);

        $invoice->seller()->associate($seller);
        $invoice->save();

        return $invoice;
    }
}
