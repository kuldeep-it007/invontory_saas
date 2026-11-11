<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterAdvanceOrdersTableAddRelatedAoId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_orders', function(Blueprint $table)
        {
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
        Schema::table('advance_orders', function (Blueprint $table){
            $table->dropColumn(['stock_point_id']);
            $table->dropColumn(['blanket_order_id']);
        });
    }
}
