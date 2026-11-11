<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `cart_items` table (R20.3, R12.4, R12.5).
 *
 * A single Product-and-quantity entry within a {@see carts} row. Each item
 * snapshots the full resolved price breakdown at the moment it was added/last
 * recalculated, so the cart can render the per-line figures from the reference
 * design without recomputing on every read:
 *
 *   rrp          — the resolved base/marked-up price per unit (R12.2, R12.4)
 *   discount_pct — the effective discount percentage off the RRP (R12.5)
 *   unit_price   — the discounted price per unit (RRP after discounts)
 *   quantity     — ordered quantity (DECIMAL(15,3), fractional UOM — R13.3)
 *   net_amount   — unit_price × quantity (pre-tax line subtotal)
 *   tax_rate     — the resolved tax percentage for the product's tax type
 *   tax_amount   — net_amount × tax_rate
 *   total_amount — net_amount + tax_amount (the line's payable amount)
 *
 * Money is DECIMAL(15,2); quantity DECIMAL(15,3); percentages DECIMAL(8,4).
 * Isolation is physical, so there is NO `tenant_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            // The product this line orders. FK attached conditionally below so
            // the schema stays installable before the catalog exists, mirroring
            // the guarded FK convention used on stock/order_lines.
            $table->foreignId('product_id');

            $table->decimal('quantity', 15, 3)->default(0);

            // Resolved price breakdown snapshot (recomputed on qty change).
            $table->decimal('rrp', 15, 2)->default(0);
            $table->decimal('discount_pct', 8, 4)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->timestamps();

            // One line per product within a cart: adding the same product again
            // bumps the quantity rather than duplicating the line.
            $table->unique(['cart_id', 'product_id'], 'cart_items_cart_product_unique');
            $table->index('product_id', 'cart_items_product_index');
        });

        if (Schema::hasTable('products')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreign('product_id', 'cart_items_product_id_foreign')
                    ->references('id')->on('products')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
