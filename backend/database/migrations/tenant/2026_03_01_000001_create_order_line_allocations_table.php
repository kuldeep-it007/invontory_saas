<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `order_line_allocations` table (R16, R20).
 *
 * When a single order line's quantity is sourced from MORE THAN ONE of the
 * seller's stock points, each source is recorded as an allocation: how much of
 * the line quantity is drawn from a given seller stock point. On confirmation
 * the seller is decremented once PER allocation (at that allocation's stock
 * point), instead of a single decrement at the seller's primary point.
 *
 * A line with no allocation rows keeps the legacy behaviour (decrement the
 * seller's primary stock point for the whole line quantity), so existing orders
 * and single-point carts are unaffected.
 *
 * Quantities use DECIMAL(15,3) to match stock/order-line precision (R13.3).
 * Isolation is physical, so there is NO `tenant_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_line_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_line_id')
                ->constrained('order_lines')
                ->cascadeOnDelete();

            // The seller stock point this portion of the line is drawn from.
            $table->foreignId('stock_point_id')
                ->constrained('stock_points')
                ->cascadeOnDelete();

            $table->decimal('quantity', 15, 3);

            $table->timestamps();

            $table->index('order_line_id', 'order_line_allocations_line_index');
            $table->unique(
                ['order_line_id', 'stock_point_id'],
                'order_line_allocations_line_point_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_allocations');
    }
};
