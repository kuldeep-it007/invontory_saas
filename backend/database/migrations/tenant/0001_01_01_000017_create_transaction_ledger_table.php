<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\StockLedger;
use App\Models\TransactionLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `transaction_ledger` table.
 *
 * The immutable, append-only running account-balance record per Buyer (R25.1,
 * R25.3). Exactly one row is appended whenever a confirmed purchase or a
 * recorded Payment affects a Buyer's account: it captures the signed `amount`
 * and the resulting `balance_after` (previous balance + signed amount — the
 * transaction-ledger balance invariant, R24.2, R25.1). A Buyer's
 * Outstanding_Balance is the latest `balance_after` for that Buyer (R25.2).
 * Rows are never updated or deleted — immutability is enforced in the
 * {@see TransactionLedger} model, mirroring the {@see StockLedger}
 * pattern.
 *
 * The Buyer is a polymorphic owner (`buyer_type`, `buyer_id`): a Distributor or
 * a Dealer. Purchase entries are positive (they increase what the Buyer owes);
 * payment entries are negative (they reduce the outstanding balance). The
 * optional `payment_id` ties a payment entry back to its {@see Payment}.
 *
 * Isolation is physical (one database per tenant), so there is NO `tenant_id`
 * column (R38.3).
 *
 * Targeted indexes (R38.4, R38.5): the Buyer morph columns + time back both the
 * per-Buyer ledger listing ordered by time and the latest-balance lookup that
 * derives Outstanding_Balance (R25.2). A `created_at` index backs tenant-wide
 * date-range account scans and the payments/collections report (R28.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_ledger', function (Blueprint $table) {
            $table->id();

            // Polymorphic Buyer owner (buyer_type, buyer_id): the Distributor /
            // Dealer whose account moved. Declared as explicit columns (NOT
            // morphs()) so no standalone (buyer_type, buyer_id) index is created
            // — the Buyer columns are the leading columns of the wider
            // per-Buyer-by-time index below, which already serves Buyer-only and
            // latest-balance prefix scans. This avoids a redundant duplicate
            // index on the same leading columns (R38.4).
            $table->string('buyer_type');
            $table->unsignedBigInteger('buyer_id');

            // Entry type: `purchase` (confirmed order increases what is owed) or
            // `payment` (recorded payment reduces the outstanding balance), plus
            // `return` adjustments (R22.3). Stored as a string so new entry types
            // can be added without a schema change.
            $table->string('entry_type');

            // Signed account amount (R25.1). DECIMAL(15,2) — monetary values in
            // the tenant's single currency (R39.1). Purchases are positive,
            // payments negative, so the running balance is previous + amount.
            $table->decimal('amount', 15, 2);

            // The Buyer's resulting Outstanding_Balance AFTER this entry is
            // applied — the balance-after invariant (R24.2, R25.1, R25.2).
            $table->decimal('balance_after', 15, 2);

            // The Payment that produced this entry, when the entry is a payment
            // (R24.2). Nullable: purchase/return entries have no payment. The FK
            // is attached below once the `payments` table exists.
            $table->unsignedBigInteger('payment_id')->nullable();

            // Free-form reference tying the entry back to its source (invoice
            // number, order number, payment reference) and the acting user id
            // (recorded-by, R24.1). Nullable for system-generated entries.
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            // Append-only: a single created_at timestamp is sufficient (there is
            // never an update), but keep the standard pair for Eloquent/report
            // ergonomics. The model blocks updates regardless.
            $table->timestamps();

            // (buyer_type, buyer_id, id): the per-Buyer ledger listing ordered
            // chronologically, and — read with ORDER BY id DESC LIMIT 1 — the
            // latest-balance lookup that derives Outstanding_Balance (R25.2).
            // Also serves Buyer-only prefix scans, so no separate morph index is
            // needed (R38.4).
            $table->index(
                ['buyer_type', 'buyer_id', 'id'],
                'transaction_ledger_buyer_id_index'
            );

            // (created_at): tenant-wide date-range account scans + the
            // payments/collections report (R28.6, R38.4).
            $table->index('created_at', 'transaction_ledger_created_index');
        });

        // Attach the payment foreign key only when the `payments` table is
        // already present. The payments migration is ordered after this one, so
        // on a fresh migrate this guard is skipped here and the FK is instead
        // created from the payments migration's up() once both tables exist.
        if (Schema::hasTable('payments')) {
            Schema::table('transaction_ledger', function (Blueprint $table) {
                $table->foreign('payment_id', 'transaction_ledger_payment_id_foreign')
                    ->references('id')->on('payments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_ledger');
    }
};
