<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `order_lines` table.
 *
 * A single Product-and-quantity entry within an Order (R20.3, R20.4). When a
 * Buyer submits a cart of N entries, exactly one Order is created with one
 * Order_Line per cart entry (R20.4). Each line captures the ordered
 * `product_id`, the `quantity`, and the resolved per-unit `unit_price` and
 * `discount` in the tenant's single currency (R12.4, R12.5, R39.1), produced by
 * the PriceResolutionService at submission time.
 *
 * Ordered AFTER the `orders` (preceding migration in this task) and `products`
 * (task 7.4) tables so the `order_id` and `product_id` references point at
 * existing rows. Isolation is physical (one database per tenant), so there is
 * NO `tenant_id` column (R38.3).
 *
 * Targeted indexes (R38.4, R38.5): the `order_id` foreign key both enforces
 * referential integrity and provides the index used to eager-load all lines of
 * an order without an N+1 (R38.1). The `product_id` foreign key provides the
 * FK/JOIN index for product-level order-line reporting (R38.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();

            // The owning Order (R20.4). The FK constraint provides the
            // (order_id) index used to eager-load an order's lines without an
            // N+1 (R38.1); deleting an order cascades to its lines.
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // The ordered Product (R20.3). FK attached conditionally below once
            // the catalog `products` table exists (task 7.4), mirroring the
            // guarded FKs on the stock tables so the schema stays installable on
            // its own while still enforcing referential integrity.
            $table->foreignId('product_id');

            // Ordered quantity. DECIMAL(15,3) matches the stock/ledger precision
            // so fractional UOM quantities carry through from cart to order to
            // the confirmation stock movement exactly (R13.3, R20.4).
            $table->decimal('quantity', 15, 3);

            // Resolved per-unit price in the tenant's single currency (R12.4,
            // R39.1). DECIMAL(15,2) is a currency amount; captured at submission
            // so the order line is a durable record of the price charged.
            $table->decimal('unit_price', 15, 2)->default(0);

            // Resolved per-unit discount amount applied to this line (R12.5).
            // Stored alongside unit_price so the line records both the charged
            // price and the discount that produced it.
            $table->decimal('discount', 15, 2)->default(0);

            $table->timestamps();

            // (product_id): FK/JOIN index + product-level order-line reporting
            // across orders (R38.2, R38.4).
            $table->index('product_id', 'order_lines_product_index');
        });

        // Attach the product foreign key only when the catalog `products` table
        // is already present (task 7.4). Mirrors the guarded FK on the stock
        // tables so the ordering schema remains installable on its own while
        // still enforcing referential integrity once products exist.
        if (Schema::hasTable('products')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->foreign('product_id', 'order_lines_product_id_foreign')
                    ->references('id')->on('products')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
