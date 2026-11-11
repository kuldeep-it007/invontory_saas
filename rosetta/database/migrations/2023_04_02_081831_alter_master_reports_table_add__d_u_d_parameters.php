<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMasterReportsTableAddDUDParameters extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_reports', function (Blueprint $table) {
            $table->integer('no_of_active_distributors',false)->nullable()->default(null)->after('no_of_distributors');
            $table->integer('no_of_duds',false)->nullable()->default(null)->after('no_of_active_dealers');
            $table->integer('no_of_active_duds',false)->nullable()->default(null)->after('no_of_duds');
            $table->integer('no_of_retail_customers',false)->nullable()->default(null)->after('no_of_active_duds');
            $table->integer('no_of_active_retail_customers',false)->nullable()->default(null)->after('no_of_retail_customers');
            $table->double('dist_sales_amount', 12,2)->default(0)->after('sales_amount');
            $table->double('dealer_sales_amount', 12,2)->default(0)->after('dist_sales_amount');
            $table->double('dud_sales_amount', 12,2)->default(0)->after('dealer_sales_amount');
            $table->double('retail_sales_amount', 12,2)->default(0)->after('dud_sales_amount');
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
            $table->dropColumn(['retail_sales_amount']);
            $table->dropColumn(['dud_sales_amount']);
            $table->dropColumn(['dealer_sales_amount']);
            $table->dropColumn(['dist_sales_amount']);
            $table->dropColumn(['no_of_active_retail_customers']);
            $table->dropColumn(['no_of_retail_customers']);
            $table->dropColumn(['no_of_active_duds']);
            $table->dropColumn(['no_of_duds']);
            $table->dropColumn(['no_of_active_distributors']);
        });
    }
}
