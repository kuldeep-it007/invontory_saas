<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the multi-distributor catalogue work.
 *
 *  - products.hsn_code: GST HSN/SAC code. Nullable in the database so existing
 *    products stay valid; the API requires a valid code on create, and on
 *    update whenever the product does not have one yet.
 *  - discounts.value_type + amount: a discount is either a percentage (the
 *    existing `percentage` column) or a fixed amount in the tenant currency.
 *    Existing rows become `percent`, so current prices do not change.
 *  - discounts.product_id: a discount can target one product (the "Product"
 *    level of the Dealer > Distributor > Product > General priority).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code', 8)->nullable()->after('sku');
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->string('value_type', 16)->default('percent')->after('customer_type');
            $table->decimal('amount', 15, 2)->nullable()->after('percentage');
            $table->foreignId('product_id')->nullable()->after('colour_id')
                ->constrained('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['value_type', 'amount']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hsn_code');
        });
    }
};
