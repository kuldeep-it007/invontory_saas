<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `active_product_attributes` table.
 *
 * Per-Buyer catalog visibility configuration (R11.4). A Buyer is polymorphic —
 * a Distributor or a Dealer — captured by the (`buyer_type`, `buyer_id`) morph.
 * Each row grants ONE Buyer visibility of ONE catalog attribute: a Category, a
 * Collection, or a Colour, captured by the (`attribute_type`, `attribute_id`)
 * morph.
 *
 * Semantics (R11.4, R11.5, R11.6):
 *   • WHERE a Buyer has at least one active-attribute row, the catalog shown to
 *     that Buyer is RESTRICTED to the configured Categories/Collections/Colours
 *     (R11.4, R11.5).
 *   • WHERE a Buyer has NO active-attribute rows at all, the restriction is
 *     absent and the Buyer sees every tenant Product (R11.6).
 *
 * Isolation is physical (one database per tenant), so there is NO `tenant_id`
 * column — a row written on the active tenant connection belongs to that
 * tenant automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_product_attributes', function (Blueprint $table) {
            $table->id();

            // Polymorphic Buyer (buyer_type, buyer_id): a Distributor or Dealer
            // whose catalog visibility is being configured (R11.4). Declared as
            // explicit columns (NOT morphs()) so no standalone
            // (buyer_type, buyer_id) index is created — those columns are the
            // leading columns of the UNIQUE composite index below, which already
            // serves the "active attributes of this buyer" lookup that drives
            // every catalog visibility query (R11.5, R38.4).
            $table->string('buyer_type');
            $table->unsignedBigInteger('buyer_id');

            // Polymorphic allowed attribute (attribute_type, attribute_id): the
            // Category, Collection, or Colour this Buyer may see (R11.4). The
            // referenced row lives in categories/collections/colours; because
            // the type is polymorphic there is no single DB foreign key, so the
            // referential link is maintained at the application layer.
            $table->string('attribute_type');
            $table->unsignedBigInteger('attribute_id');

            $table->timestamps();

            // UNIQUE(buyer_type, buyer_id, attribute_type, attribute_id): a
            // Buyer may grant the same attribute at most once (idempotent
            // configuration). The leading (buyer_type, buyer_id) prefix is the
            // exact WHERE clause used to (a) detect whether a Buyer is
            // configured at all (R11.6) and (b) collect a configured Buyer's
            // allowed attributes to restrict the catalog (R11.4, R11.5, R38.4).
            $table->unique(
                ['buyer_type', 'buyer_id', 'attribute_type', 'attribute_id'],
                'active_product_attributes_buyer_attribute_unique'
            );

            // (attribute_type, attribute_id): the reverse lookup "which buyers
            // reference this attribute", used to cascade-clean configuration
            // when a Category/Collection/Colour is removed (R38.4).
            $table->index(
                ['attribute_type', 'attribute_id'],
                'active_product_attributes_attribute_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_product_attributes');
    }
};
