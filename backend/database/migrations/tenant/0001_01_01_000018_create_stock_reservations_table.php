<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `stock_reservations` table (R23.2, R23.4, R23.5).
 *
 * A reservation BLOCKS a quantity of a `stock` row for an in-progress or
 * advance order WITHOUT moving the owned on-hand quantity (R23.4, design
 * "Stock blocking/reservation"). Available quantity for every other order is
 * therefore `on-hand − Σ(active reservations)`; a reservation with status
 * `active` subtracts from available while one with status `released` does not,
 * so cancelling or expiring an order releases its blocked stock back to
 * available (R23.5).
 *
 * The row references the `stock` row it blocks (`stock_id`) and the `order` it
 * is held for (`order_id`), carries the reserved `quantity` as DECIMAL(15,3)
 * (matching the stock/ledger precision so fractional UOM reservations are
 * exact — R13.3), the `status` (active|released), and an OPTIONAL `expires_at`
 * for advance/blanket reservations that lapse on a schedule (R23.1, R23.2).
 *
 * Isolation is physical, so there is NO `tenant_id` column. This migration is
 * ordered after `stock` (task 7.11) and `orders` (task 9.1); it attaches the
 * FKs defensively so it stays installable alongside the parallel order schema.
 *
 * Targeted index (R38.4): `(stock_id, status)` is the exact predicate of the
 * availability computation — sum the `quantity` of the `active` reservations
 * for a stock row — so the available-quantity read is index-served and never
 * scans released history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            return;
        }

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();

            // The stock row this reservation blocks (R23.4). FK attached below
            // once `stock` exists so availability joins stay referentially
            // sound and a reservation is removed if its stock row is.
            $table->unsignedBigInteger('stock_id');

            // The order the reservation is held for (R23.2). Nullable so a
            // reservation can be placed before the order row is finalised in
            // edge flows; normally set to the owning order. FK attached below.
            $table->unsignedBigInteger('order_id')->nullable();

            // The reserved (blocked) quantity. DECIMAL(15,3) matches stock
            // precision so the available = on-hand − Σ(active) arithmetic is
            // exact for fractional UOM (R13.3, R23.4).
            $table->decimal('quantity', 15, 3);

            // Lifecycle status: 'active' blocks stock (subtracts from
            // available), 'released' does not (R23.4, R23.5). Defaults to
            // 'active' because a reservation blocks stock the moment it is made.
            $table->string('status')->default('active');

            // Optional expiry for advance/blanket reservations that lapse on a
            // schedule (R23.1, R23.2); null for a reservation with no scheduled
            // expiry. An expired-but-still-active reservation is released by the
            // service (R23.5).
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // (stock_id, status): the availability computation sums the reserved
            // quantity of the ACTIVE reservations for a stock row — this exact
            // composite is the WHERE/aggregate predicate, so it is index-served
            // and never scans released history (R23.4, R38.4).
            $table->index(['stock_id', 'status'], 'stock_reservations_stock_status_index');

            // (order_id): release-by-order reads every reservation held for an
            // order when it is cancelled/expired (R23.5), and the FK lookup
            // column (R38.2, R38.4).
            $table->index('order_id', 'stock_reservations_order_index');
        });

        if (Schema::hasTable('stock')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->foreign('stock_id', 'stock_reservations_stock_id_foreign')
                    ->references('id')->on('stock')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->foreign('order_id', 'stock_reservations_order_id_foreign')
                    ->references('id')->on('orders')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
