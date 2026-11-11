<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `payment_allocations` table.
 *
 * The allocation of a Payment to one or more invoices (R24.3). A single
 * {@see Payment} may be split across several invoices (partial
 * payments and multi-invoice allocation), and the sum of a payment's allocation
 * amounts never exceeds the payment amount — that invariant is enforced in
 * {@see PaymentService} (R24.3). Each row records how much of a
 * given payment was applied to a given invoice.
 *
 * Isolation is physical (one database per tenant), so there is NO `tenant_id`
 * column (R38.3).
 *
 * The `invoice_id` references the `invoices` table produced by the invoice
 * generation work (task 9.8). That table may not yet exist when this migration
 * runs, so — mirroring the guarded FK on `stock_ledger.product_id` — the
 * foreign key is only attached when `invoices` is already present, keeping this
 * schema installable on its own while still enforcing referential integrity
 * once invoices exist.
 *
 * Targeted indexes (R38.4): `payment_id` backs "allocations of a payment" and
 * the per-payment sum used to enforce the allocation ceiling; `invoice_id`
 * backs "allocations against an invoice" and the per-invoice allocated-total
 * used to derive each invoice's outstanding amount (R24.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();

            // FK to the owning Payment (R24.3). Indexed by the FK constraint;
            // backs the per-payment allocation sum that enforces the ceiling
            // (sum of allocations <= payment amount).
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            // FK to the invoice this slice of the payment is applied to (R24.3).
            // References the `invoices` table (task 9.8); the FK is attached
            // below only when that table already exists. Indexed explicitly so
            // the per-invoice allocated total (invoice outstanding) is fast even
            // before the FK (and its index) is attached (R38.4).
            $table->unsignedBigInteger('invoice_id');

            // The amount of the payment applied to this invoice in the tenant's
            // single currency (R24.3, R39.1). DECIMAL(15,2); always positive and
            // never more than the invoice's outstanding amount.
            $table->decimal('amount', 15, 2);

            $table->timestamps();

            // (invoice_id): allocations against an invoice + the per-invoice
            // allocated total that derives the invoice's outstanding amount
            // (R24.3, R38.4). Also serves as the FK/JOIN index when the FK is
            // not yet attached.
            $table->index('invoice_id', 'payment_allocations_invoice_index');
        });

        // Attach the invoice foreign key only when the `invoices` table is
        // already present (task 9.8). Mirrors the guarded FK on
        // `stock_ledger.product_id`.
        if (Schema::hasTable('invoices')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->foreign('invoice_id', 'payment_allocations_invoice_id_foreign')
                    ->references('id')->on('invoices')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
