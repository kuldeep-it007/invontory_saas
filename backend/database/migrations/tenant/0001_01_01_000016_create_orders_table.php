<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `orders` table (R20.1–R20.4, R21.1).
 *
 * An Order is a purchase placed by a Buyer against a Seller carrying one or
 * more Order_Lines. Both the buyer and the seller are polymorphic
 * (`buyer_type`/`buyer_id`, `seller_type`/`seller_id`) so a Company→Distributor
 * order and a Distributor→Dealer order share one table: a Distributor orders
 * against the Company catalogue (buyer = Distributor, seller = Company) and a
 * Dealer orders against its serving Distributor (buyer = Dealer, seller =
 * Distributor) (R20.1, R20.2).
 *
 * The `status` column holds the current lifecycle state and covers the full
 * set Draft, Placed, Confirmed, Dispatched, Delivered, Closed, Cancelled
 * (R21.1); legal moves between states are governed by
 * {@see \App\Services\OrderStateMachine}. `order_type` distinguishes standard
 * from advanced order kinds (blanket/advance/depot — R23), and `total` is the
 * order's monetary total in the tenant's single currency.
 *
 * Isolation is physical, so there is NO `tenant_id` column.
 *
 * This migration is written defensively because the full orders/order_lines
 * schema is authored in parallel (task 9.1): it creates the table only when it
 * does not already exist, so whichever migration runs first owns the schema and
 * the other is a no-op. Targeted indexes (R38.4) cover the status filter and
 * the buyer/seller morph lookups used by order listings and the state machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Polymorphic Buyer (Distributor or Dealer) placing the order
            // (R20.1, R20.2). Explicit columns + a dedicated index below.
            $table->string('buyer_type');
            $table->unsignedBigInteger('buyer_id');

            // Polymorphic Seller (Company or Distributor) the order is placed
            // against (R20.1, R20.2).
            $table->string('seller_type');
            $table->unsignedBigInteger('seller_id');

            // Current lifecycle state (R21.1). Defaults to the canonical
            // OrderStatus::DRAFT ('draft') — a cart becomes a draft/placed order
            // on submission (R20.4). The default and every stored value use the
            // SAME lowercase vocabulary the OrderStateMachine transition map is
            // keyed on, so transitions resolve for a persisted order (R21.4).
            $table->string('status')->default('draft');

            // Order kind: 'standard' plus advanced types (blanket/advance/depot
            // — R23). Kept as a string so new types need no migration.
            $table->string('order_type')->default('standard');

            // Monetary total in the tenant's single currency (R39.1). Credit
            // checks at confirmation compare against this (R26.2).
            $table->decimal('total', 15, 2)->default(0);

            $table->timestamps();

            // (status): order listings and the state machine filter heavily by
            // status (e.g. the seller's Placed queue awaiting approval — R21.2);
            // indexed per R38.4.
            $table->index('status', 'orders_status_index');

            // (buyer_type, buyer_id): "orders for this buyer" listings and the
            // buyer's outstanding/ledger joins (R38.4).
            $table->index(['buyer_type', 'buyer_id'], 'orders_buyer_index');

            // (seller_type, seller_id): the seller's order queue (R21.2, R38.4).
            $table->index(['seller_type', 'seller_id'], 'orders_seller_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
