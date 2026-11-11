<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: add `allocations` JSON to `cart_items` (R16, R20).
 *
 * Holds the buyer's optional per-stock-point source split for the line as a
 * flexible JSON array of `{stock_point_id, quantity}` entries. Null/empty means
 * "source from the seller's primary stock point" (the legacy behaviour). On
 * checkout the allocations flow into `order_line_allocations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('cart_items', 'allocations')) {
                $table->json('allocations')->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            if (Schema::hasColumn('cart_items', 'allocations')) {
                $table->dropColumn('allocations');
            }
        });
    }
};
