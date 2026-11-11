<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: add a human-facing `order_number` to `orders` (R20).
 *
 * The auto-increment `id` stays the internal primary key, but orders are now
 * surfaced by a UNIQUE, minimum-8-digit `order_number` so each order carries a
 * stable, business-friendly identifier in listings, lifecycle confirmations,
 * and documents. The number is derived from the id with a fixed base offset
 * (10_000_000 + id), which is always ≥ 8 digits and inherently unique. New
 * orders generate it on create (see the Order model's `creating` hook); this
 * migration adds the column and backfills every existing order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'order_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // Nullable first so the column can be added to a populated table;
            // backfilled below, then a unique index is attached.
            $table->string('order_number', 32)->nullable()->after('id');
        });

        // Backfill existing orders with the same deterministic scheme the model
        // uses for new orders: an 8+ digit number offset from the id.
        DB::table('orders')
            ->whereNull('order_number')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($row) {
                DB::table('orders')
                    ->where('id', $row->id)
                    ->update(['order_number' => (string) (10_000_000 + (int) $row->id)]);
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number', 'orders_order_number_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'order_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_order_number_unique');
            $table->dropColumn('order_number');
        });
    }
};
