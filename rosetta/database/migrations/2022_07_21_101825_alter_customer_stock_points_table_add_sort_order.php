<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterCustomerStockPointsTableAddSortOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_stock_points', function(Blueprint $table)
        {
            $table->integer('sort_order',false)->nullable()->default(0)->after('stock_point_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_stock_points', function (Blueprint $table){
            $table->dropColumn(['sort_order']);
        });
    }
}
