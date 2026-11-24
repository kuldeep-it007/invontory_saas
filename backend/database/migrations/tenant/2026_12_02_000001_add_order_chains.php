<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the multi-distributor catalogue work: chain orders.
 *
 * When a dealer orders stock its distributor does not hold (or picks another
 * holder's stock), two linked orders are created:
 *
 *   Order 1 (SO-…): dealer → its distributor, every line;
 *   Order 2 (PO-…): distributor → the stock holder, only the chained lines.
 *
 *  - orders.chain_ref: shared reference of every order in one chain.
 *  - orders.parent_order_id: Order 2 points at the Order 1 it supplies.
 *  - cart_items.source_type/source_id: the upstream holder a dealer picked
 *    for a line (null = the distributor's own stock, chained automatically
 *    only for any shortfall).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('chain_ref', 32)->nullable()->after('order_number');
            $table->foreignId('parent_order_id')->nullable()->after('chain_ref')
                ->constrained('orders')->nullOnDelete();
            $table->index('chain_ref', 'orders_chain_ref_index');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('source_type')->nullable()->after('seller_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn(['source_type', 'source_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_chain_ref_index');
            $table->dropConstrainedForeignId('parent_order_id');
            $table->dropColumn('chain_ref');
        });
    }
};
