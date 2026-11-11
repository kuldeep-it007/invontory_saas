<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `prices` table.
 *
 * A configured Price for the catalog, defined at either the Product or the
 * Collection level (R12.1). A Product-level price overrides the Collection-level
 * price for that product when both exist (R12.4). A markup or MUP (margin on
 * price) may be configured per Collection and is carried on the Collection-level
 * price row (R12.2).
 *
 * All monetary amounts are expressed in the Tenant's single configured currency
 * (R39.1), which lives on the central `tenants` row — there is no per-price
 * currency column. Isolation is physical (one database per tenant), so there is
 * NO `tenant_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();

            // Price level: `product` or `collection` (R12.1). Constrained to the
            // two valid levels at the database layer; the model/validation layer
            // mirrors the constraint.
            $table->enum('level', ['product', 'collection']);

            // The id of the Product (when level=product) or Collection (when
            // level=collection) this price applies to (R12.1). Not a hard FK
            // because the referenced table varies by `level`; the resolver reads
            // it scoped by `level`.
            $table->unsignedBigInteger('ref_id');

            // Base price amount in the tenant's single currency (R12.4, R39.1).
            $table->decimal('amount', 15, 2);

            // Markup / MUP configured per Collection (R12.2). NULL = no markup.
            // `markup` adds a margin on top of the base; `mup` (markup on price)
            // is applied the same cumulative way by the resolver. Only
            // meaningful for collection-level rows.
            $table->enum('markup_type', ['markup', 'mup'])->nullable();

            // The markup/MUP percentage (e.g. 10.00 = +10%) applied when
            // `markup_type` is set (R12.2). NULL/absent means no markup.
            $table->decimal('markup_value', 8, 4)->nullable();

            $table->timestamps();

            // (level, ref_id) is the resolver's lookup key: "the price for this
            // product" / "the price for this collection" is read by level +
            // ref_id on every order-line price resolution (R12.4, R38.4). Unique
            // so there is at most one price row per (level, ref) target.
            $table->unique(['level', 'ref_id'], 'prices_level_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
