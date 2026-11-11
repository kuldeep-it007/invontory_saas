<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `stock` table.
 *
 * The per-(owner, product, stock_point) on-hand quantity that makes the
 * Platform the single source of truth for inventory (R13.1, R13.3). The owner
 * is polymorphic (`stockable_type`, `stockable_id`): a Company or Distributor,
 * or a Dealer only when dealer stock-holding is enabled for the tenant (R13.4,
 * R13.5). Dealers hold no stock when the toggle is disabled — enforced in the
 * application/model layer, so no stock rows are created for them (R13.6, R13.7).
 *
 * Ordered AFTER the `products` table (task 7.4) because each stock row
 * references a product; it also references a `stock_points` row (created by the
 * preceding migration in this task). Isolation is physical, so there is NO
 * `tenant_id` column.
 *
 * Targeted indexes (R38.4): the owner morph columns, `product_id`, and
 * `stock_point_id` are the columns used in the WHERE/JOIN clauses of stock
 * lookups, movement locking, and stock-on-hand reports — each index below is
 * justified against those access patterns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (stockable_type, stockable_id): Company /
            // Distributor / (optionally) Dealer. Declared as explicit columns
            // (NOT morphs()) so no standalone (stockable_type, stockable_id)
            // index is created — the owner columns are the leading columns of
            // the wider composite lookup index defined below, which already
            // serves owner-only and owner+product prefix scans. This avoids a
            // redundant duplicate index on the same leading columns (R38.4).
            $table->string('stockable_type');
            $table->unsignedBigInteger('stockable_id');

            // The product this quantity is held for (R13.3). FK is attached
            // conditionally below once the `products` table exists (task 7.4).
            $table->foreignId('product_id');

            // The stock point holding the quantity (R13.3). References the
            // stock_points table created earlier in this task.
            $table->foreignId('stock_point_id')
                ->constrained('stock_points')
                ->cascadeOnDelete();

            // On-hand quantity. DECIMAL(15,3) matches the ledger precision so
            // fractional UOM quantities are represented exactly (R13.3).
            $table->decimal('quantity', 15, 3)->default(0);

            // Per-(product, stock_point) low-stock threshold used to raise
            // low-stock alerts/reports (R19.1); nullable when unset.
            $table->decimal('low_stock_threshold', 15, 3)->nullable();

            $table->timestamps();

            // Composite lookup/locking key (stockable_type, stockable_id,
            // product_id, stock_point_id): the exact row targeted by stock
            // movement (SELECT ... FOR UPDATE) and the stock-on-hand lookup for
            // an owner's product at a stock point. UNIQUE because at most one
            // stock row may exist per (owner, product, stock_point). This single
            // index also serves owner-only and owner+product prefix scans, so no
            // separate morph index is needed (R16, R28.1, R38.4).
            $table->unique(
                ['stockable_type', 'stockable_id', 'product_id', 'stock_point_id'],
                'stock_owner_product_point_unique'
            );

            // (product_id): stock-on-hand across all owners/points for a product
            // and the FK lookup column (R38.2, R38.4).
            $table->index('product_id', 'stock_product_index');

            // (stock_point_id) is already indexed by the foreign-key constraint
            // above, serving the per-stock-point stock-on-hand report (R28.1).
        });

        // Attach the product foreign key only when the catalog `products` table
        // is already present (task 7.4). This migration is ordered after
        // products; the guard keeps the stock schema installable on its own
        // while still enforcing referential integrity once products exist.
        if (Schema::hasTable('products')) {
            Schema::table('stock', function (Blueprint $table) {
                $table->foreign('product_id', 'stock_product_id_foreign')
                    ->references('id')->on('products')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
