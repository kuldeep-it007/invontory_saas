<?php

declare(strict_types=1);

use App\Models\StockLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `stock_ledger` table.
 *
 * The immutable, append-only record of every stock movement (R15.1, R15.2).
 * `StockMovementService` writes exactly one ledger row per movement inside the
 * same transaction that mutates the `stock` balance, capturing the signed
 * movement `quantity`, the `balance_after` (equal to the owner's post-movement
 * `stock.quantity` for that product/stock_point — the balance-after invariant,
 * R15.3), the `movement_type`, `reason`, acting `actor_id`, `reference`, and a
 * timestamp. Rows are never updated or deleted — immutability is enforced in
 * the {@see StockLedger} model.
 *
 * Ordered AFTER the `stock`, `stock_points`, and `products` tables so the
 * owner/product/stock_point references point at existing rows. Isolation is
 * physical (one database per tenant), so there is NO `tenant_id` column
 * (R38.3).
 *
 * Targeted indexes (R38.4): the owner morph columns + product + time back the
 * stock-ledger movement report ordered by time per owner/product (R28.2), and
 * a `created_at` index backs date-range ledger scans within the tenant. The
 * `product_id` and `stock_point_id` foreign keys additionally provide FK/JOIN
 * indexes (R38.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (stockable_type, stockable_id): the Company /
            // Distributor / (optionally) Dealer whose stock moved — mirrors the
            // `stock` table's owner columns. Declared as explicit columns (NOT
            // morphs()) so no standalone (stockable_type, stockable_id) index is
            // created: the owner columns are the leading columns of the wider
            // movement-report index below, which already serves owner-only and
            // owner+product prefix scans. This avoids a redundant duplicate
            // index on the same leading columns (R38.4).
            $table->string('stockable_type');
            $table->unsignedBigInteger('stockable_id');

            // The product whose quantity moved (R15.1). FK attached below once
            // the catalog `products` table exists (task 7.4).
            $table->foreignId('product_id');

            // The stock point at which the movement occurred (R15.1). References
            // the stock_points table; the FK constraint provides its index.
            $table->foreignId('stock_point_id')
                ->constrained('stock_points')
                ->cascadeOnDelete();

            // Movement type: opening|received|adjustment_in|adjustment_out|
            // sale_out|purchase_in|transfer_in|transfer_out|reversal|return_in,
            // etc. (R14.1, R15.1). Stored as a string so new movement types can
            // be added without a schema change.
            $table->string('movement_type');

            // Signed movement quantity (R15.1). DECIMAL(15,3) matches the
            // `stock.quantity` precision so fractional UOM movements and the
            // balance-after invariant are represented exactly (R15.3).
            $table->decimal('quantity', 15, 3);

            // The owner's resulting Stock quantity AFTER this movement is
            // applied — the balance-after invariant (R15.3).
            $table->decimal('balance_after', 15, 3);

            // Reason captured for adjustments/overrides and surfaced in reports
            // and the audit trail (R14.3, R34.3); nullable for system-generated
            // movements that need no explicit reason.
            $table->string('reason')->nullable();

            // The acting user id (actor) that caused the movement (R15.1).
            // Nullable for system/automated movements with no interactive user.
            $table->unsignedBigInteger('actor_id')->nullable();

            // Free-form reference tying the movement back to its source (e.g.
            // an order number, transfer id, or adjustment reference) (R15.1).
            $table->string('reference')->nullable();

            // Append-only: a single created_at timestamp is sufficient (there is
            // never an update), but keep the standard pair for Eloquent/report
            // ergonomics. The model blocks updates regardless.
            $table->timestamps();

            // (stockable_type, stockable_id, product_id, created_at): the
            // stock-ledger movement report ordered by time for a given owner and
            // product (R28.2). Also serves owner-only and owner+product prefix
            // scans, so no separate morph index is needed (R38.4).
            $table->index(
                ['stockable_type', 'stockable_id', 'product_id', 'created_at'],
                'stock_ledger_owner_product_created_index'
            );

            // (created_at): tenant-wide date-range ledger scans (R28.2, R38.4).
            $table->index('created_at', 'stock_ledger_created_index');

            // (product_id): FK/JOIN index + ledger movements for a product
            // across owners/points (R38.2, R38.4).
            $table->index('product_id', 'stock_ledger_product_index');
        });

        // Attach the product foreign key only when the catalog `products` table
        // is already present (task 7.4). Mirrors the guarded FK on the `stock`
        // table so the ledger schema remains installable on its own while still
        // enforcing referential integrity once products exist.
        if (Schema::hasTable('products')) {
            Schema::table('stock_ledger', function (Blueprint $table) {
                $table->foreign('product_id', 'stock_ledger_product_id_foreign')
                    ->references('id')->on('products')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
    }
};
