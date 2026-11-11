<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rosetta-style discount scoping (R12.3, R12.5).
 *
 * Rosetta resolves a discount most-specific-first across customer_type +
 * optional customer + catalog scope (category → collection → colour). We add
 * the same scoping dimensions to our `discounts` table (dropping Rosetta's
 * geographic region/state/city/postcode tiers, which our model does not have):
 *
 *   - `customer_type` — the buyer role the discount applies to (e.g.
 *     Distributor/Dealer), nullable = any customer type.
 *   - `category_id` / `collection_id` / `colour_id` — optional catalog scope.
 *     A NULL at a level means "applies to all" at that level. More-specific
 *     rows (deeper non-null catalog scope, or a set buyer) win during
 *     resolution.
 *
 * Catalog scope columns are nullable FKs that cascade on delete so removing a
 * catalog level cleans up its scoped discounts. Tenant-owned (no tenant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('discounts', 'customer_type')) {
                $table->string('customer_type', 64)->nullable()->after('type');
            }
            if (! Schema::hasColumn('discounts', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('buyer_id')
                    ->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('discounts', 'collection_id')) {
                $table->foreignId('collection_id')->nullable()->after('category_id')
                    ->constrained('collections')->nullOnDelete();
            }
            if (! Schema::hasColumn('discounts', 'colour_id')) {
                $table->foreignId('colour_id')->nullable()->after('collection_id')
                    ->constrained('colours')->nullOnDelete();
            }

            // The resolver filters by type + active + catalog scope; index the
            // catalog-scope columns so most-specific-first resolution avoids a
            // table scan (R12.5, R38.4).
            $table->index(['category_id', 'collection_id', 'colour_id'], 'discounts_catalog_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            $table->dropIndex('discounts_catalog_scope_index');
            foreach (['colour_id', 'collection_id', 'category_id'] as $col) {
                if (Schema::hasColumn('discounts', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
            if (Schema::hasColumn('discounts', 'customer_type')) {
                $table->dropColumn('customer_type');
            }
        });
    }
};
