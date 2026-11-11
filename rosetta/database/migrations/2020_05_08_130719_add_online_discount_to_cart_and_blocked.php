<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOnlineDiscountToCartAndBlocked extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->float('onlineDiscount')->default(0)->after('discount');
        });

        Schema::table('blocked_items', function (Blueprint $table) {
            $table->float('onlineDiscount')->default(0)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table){
            $table->dropColumn(['onlineDiscount']);
        });
        Schema::table('blocked_items', function (Blueprint $table){
            $table->dropColumn(['onlineDiscount']);
        });
    }
}
