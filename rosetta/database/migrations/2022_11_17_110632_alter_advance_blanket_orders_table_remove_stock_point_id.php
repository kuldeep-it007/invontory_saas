<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterAdvanceBlanketOrdersTableRemoveStockPointId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_blanket_orders', function (Blueprint $table) {
            $table->dropColumn(['stock_point_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_blanket_orders', function (Blueprint $table) {
            $table->integer('stock_point_id',false)->nullable()->default(null);
        });
    }
}
