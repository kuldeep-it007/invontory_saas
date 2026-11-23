<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the multi-distributor catalogue work: network ordering.
 *
 * A cart line now records who sells it: the holder of the stock points the
 * buyer chose (the Company or a distributor). NULL keeps the previous default
 * seller, so open carts keep working. On submit the cart becomes one order
 * per seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('seller_type')->nullable()->after('product_id');
            $table->unsignedBigInteger('seller_id')->nullable()->after('seller_type');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn(['seller_type', 'seller_id']);
        });
    }
};
