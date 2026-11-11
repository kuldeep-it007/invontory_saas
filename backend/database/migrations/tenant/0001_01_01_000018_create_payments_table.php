<?php

declare(strict_types=1);

use App\Services\PaymentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a named foreign key constraint exists on a table in the active
 * (tenant) connection's database. Used to make the deferred cross-table FK
 * between `transaction_ledger` and `payments` idempotent regardless of the
 * order the two migrations run in. Defined BEFORE the migration's `return`
 * because a migration file stops executing at the returned anonymous class, so
 * anything declared after the return would never be reached.
 */
if (! function_exists('paymentsMigrationForeignKeyExists')) {
    function paymentsMigrationForeignKeyExists(string $table, string $constraint): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        $rows = Schema::getConnection()->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS '
            .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? '
            .'AND CONSTRAINT_TYPE = ?',
            [$database, $table, $constraint, 'FOREIGN KEY']
        );

        return $rows !== [];
    }
}

/**
 * Tenant database: `payments` table.
 *
 * A manually recorded Payment applied against a Buyer's outstanding balance
 * (R24.1). Each payment captures its `amount`, `method`, `reference`, payment
 * `paid_at` date, and the `recorded_by` acting-user identity (R24.1). Recording
 * a payment also appends a `transaction_ledger` entry and updates the running
 * balance (R24.2) and may be allocated across one or more invoices via
 * `payment_allocations` (R24.3) — those side effects are orchestrated by
 * {@see PaymentService}.
 *
 * The Buyer is a polymorphic owner (`buyer_type`, `buyer_id`): a Distributor or
 * a Dealer. Isolation is physical (one database per tenant), so there is NO
 * `tenant_id` column (R38.3).
 *
 * Targeted indexes (R38.4): the Buyer morph columns + date back the per-Buyer
 * payment history ordered by date, and a `paid_at` index backs the
 * payments/collections report date-range scans (R28.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Polymorphic Buyer owner (buyer_type, buyer_id): the Distributor /
            // Dealer the payment is recorded against. Declared as explicit
            // columns (NOT morphs()) so no standalone (buyer_type, buyer_id)
            // index is created — the Buyer columns lead the wider
            // per-Buyer-by-date index below (R38.4).
            $table->string('buyer_type');
            $table->unsignedBigInteger('buyer_id');

            // The payment amount in the tenant's single currency (R24.1, R39.1).
            // DECIMAL(15,2) for exact monetary values; always positive.
            $table->decimal('amount', 15, 2);

            // Payment method: cash|cheque|bank_transfer|upi|card|other (R24.1).
            // Stored as a string so new methods need no schema change.
            $table->string('method');

            // Free-form external reference for the payment, e.g. a cheque number
            // or bank transaction id (R24.1). Nullable.
            $table->string('reference')->nullable();

            // The date the payment was made (R24.1) — distinct from created_at
            // (when it was recorded in the platform).
            $table->date('paid_at');

            // The acting user that recorded the payment — recorded-by identity
            // (R24.1). Nullable for system/imported payments.
            $table->unsignedBigInteger('recorded_by')->nullable();

            $table->timestamps();

            // (buyer_type, buyer_id, paid_at): per-Buyer payment history ordered
            // by payment date (R24.1, R38.4). Also serves Buyer-only prefix
            // scans, so no separate morph index is needed.
            $table->index(
                ['buyer_type', 'buyer_id', 'paid_at'],
                'payments_buyer_paid_at_index'
            );

            // (paid_at): tenant-wide date-range scans for the payments/
            // collections report (R28.6, R38.4).
            $table->index('paid_at', 'payments_paid_at_index');
        });

        // Now that both tables exist, attach the transaction_ledger → payments
        // foreign key the ledger migration (ordered before this one) deferred.
        if (Schema::hasTable('transaction_ledger')
            && ! paymentsMigrationForeignKeyExists('transaction_ledger', 'transaction_ledger_payment_id_foreign')
        ) {
            Schema::table('transaction_ledger', function (Blueprint $table) {
                $table->foreign('payment_id', 'transaction_ledger_payment_id_foreign')
                    ->references('id')->on('payments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Drop the deferred FK before the referenced table disappears so a
        // rollback is clean.
        if (Schema::hasTable('transaction_ledger')
            && paymentsMigrationForeignKeyExists('transaction_ledger', 'transaction_ledger_payment_id_foreign')
        ) {
            Schema::table('transaction_ledger', function (Blueprint $table) {
                $table->dropForeign('transaction_ledger_payment_id_foreign');
            });
        }

        Schema::dropIfExists('payments');
    }
};
