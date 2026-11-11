<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `carts` table (R20.3).
 *
 * A durable, database-backed shopping cart owned by a polymorphic Buyer (a
 * Distributor or a Dealer — R20.1, R20.2). A cart accumulates multiple line
 * items before being submitted into exactly one Order (R20.3, R20.4). Keeping
 * the cart in the database (rather than only client-side state) lets a buyer
 * build an order across sessions and lets the server be the single source of
 * truth for per-line pricing/discount/tax calculations.
 *
 * Exactly one OPEN cart exists per buyer at a time; once submitted the cart is
 * marked `submitted` and a fresh open cart is started on the next add. Isolation
 * is physical (one database per tenant), so there is NO `tenant_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // The Buyer that owns this cart (Distributor or Dealer — R20.1,
            // R20.2). Polymorphic because a Buyer is one of two owner types.
            $table->nullableMorphs('buyer');

            // Lifecycle: `open` while being built, `submitted` once turned into
            // an order. Only one open cart per buyer is expected.
            $table->string('status')->default('open');

            // The order this cart became, once submitted (R20.4). Nullable while
            // the cart is still open.
            $table->unsignedBigInteger('order_id')->nullable();

            $table->timestamps();

            // The hot lookup is "the open cart for this buyer"; index the buyer
            // morph + status pair that drives it.
            $table->index(['buyer_type', 'buyer_id', 'status'], 'carts_buyer_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
