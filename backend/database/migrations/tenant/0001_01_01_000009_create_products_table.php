<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `products` table.
 *
 * A sellable item in the catalog carrying SKU, tax type, and unit of measure
 * (R11.2). A Product references its Collection and Colour (Category is reached
 * through the Collection). Tenant-owned, no `tenant_id` column: isolation is
 * physical, so a plain UNIQUE(`sku`) index makes SKU uniqueness per-tenant
 * automatically — the same SKU may exist in a different tenant's database but
 * never twice within one tenant (R11.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // SKU. UNIQUE within the tenant database (R11.1); the unique index
            // also serves fast SKU lookups during ordering/stock operations.
            $table->string('sku');

            $table->string('name');

            // FK -> collections.id. constrained() adds the FK + index; the
            // catalog browse "filter by collection" query is covered (R11.2,
            // R38.4). The owning Category is reached via the Collection.
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();

            // FK -> colours.id. constrained() adds the FK + index so colour
            // filtering/joins avoid a table scan (R11.2, R38.4).
            $table->foreignId('colour_id')->constrained('colours')->cascadeOnDelete();

            // Tax type (e.g. GST slab identifier) and unit of measure carried
            // on every product (R11.2).
            $table->string('tax_type');
            $table->string('uom');

            $table->timestamps();

            // UNIQUE(sku): enforces per-tenant SKU uniqueness (R11.1) and backs
            // the direct SKU lookup used across ordering/inventory.
            $table->unique('sku', 'products_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
