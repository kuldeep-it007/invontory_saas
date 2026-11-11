<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPurchaseOrdersAddBlanketOrderId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->integer('blanket_order_id',false)->nullable()->default(null)->after('navision_order_number');
            $table->integer('stock_point_id',false)->nullable()->default(null)->after('blanket_order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('purchase_orders', function (Blueprint $table){
            $table->dropColumn(['blanket_order_id']);
            $table->dropColumn(['stock_point_id']);
        });
    }
}
