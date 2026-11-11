<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMasterReportsTableAddCompSalesCol extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('master_reports', function (Blueprint $table) {
            $table->double('comp_dist_sales_amount', 12,2)->default(0)->after('retail_sales_amount');
            $table->integer('no_of_dud_orders',false)->nullable()->default(null)->after('no_of_orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_reports', function (Blueprint $table){
            $table->dropColumn(['no_of_dud_orders']);
            $table->dropColumn(['comp_dist_sales_amount']);
        });
    }
}
