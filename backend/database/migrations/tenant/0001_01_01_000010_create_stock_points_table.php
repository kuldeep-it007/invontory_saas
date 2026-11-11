<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `stock_points` table.
 *
 * A named physical/logical location that holds Stock, owned polymorphically by
 * a Company or a Distributor — and optionally a Dealer only when dealer
 * stock-holding is enabled for the tenant (R13.4, R13.5, R13.7). An owner may
 * have one or more Stock_Points, each carrying a sort order and a display name
 * (R13.8).
 *
 * Isolation is physical (one database per tenant), so there is NO `tenant_id`
 * column and no tenant discriminator index — the row already lives in the
 * active tenant's database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_points', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (owner_type, owner_id): Company or Distributor,
            // or Dealer when dealer stock-holding is enabled (R13.4, R13.5,
            // R13.7). morphs() creates the composite index
            // (owner_type, owner_id) used to list/resolve an owner's stock
            // points — the owner morph columns appear in WHERE/JOIN for every
            // "stock points of this owner" lookup (R38.4).
            $table->morphs('owner');

            // Display name shown in UI and reports (R13.8).
            $table->string('name');

            // Deterministic ordering of an owner's stock points (R13.8).
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_points');
    }
};
