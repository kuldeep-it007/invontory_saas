<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterAdvanceItemsTableAddStockPoint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_items', function(Blueprint $table)
        {
            $table->integer('stock_point_id',false)->nullable()->default(null)->after('nav_intransit_po'); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_items', function (Blueprint $table){
            $table->dropColumn(['stock_point_id']);
        });
    }
}
