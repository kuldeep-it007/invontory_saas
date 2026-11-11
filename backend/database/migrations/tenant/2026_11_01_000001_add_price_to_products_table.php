<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: add a nullable `price` to `products`.
 *
 * The base selling amount is maintained alongside the product's details so the
 * product list and the pricing screen can show the configured amount without a
 * separate Price row lookup (R11.3, R12). DECIMAL(15,2) matches the monetary
 * precision used across the tenant schema; nullable so existing products remain
 * valid until a price is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
