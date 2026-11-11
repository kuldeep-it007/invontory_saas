<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMasterReportsTableAddGrowthPercentColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_reports', function (Blueprint $table) {
            $table->integer('percent_growth_dealers',false)->nullable()->default(null)->after('no_of_dealers');
            $table->integer('percent_growth_duds',false)->nullable()->default(null)->after('no_of_duds');
            $table->integer('percent_growth_retail_customers',false)->nullable()->default(null)->after('no_of_retail_customers');
            $table->integer('percent_growth_active_dealers',false)->nullable()->default(null)->after('no_of_active_dealers');
            $table->integer('percent_growth_active_duds',false)->nullable()->default(null)->after('no_of_active_duds');
            $table->integer('percent_growth_active_retail_customers',false)->nullable()->default(null)->after('no_of_active_retail_customers');
            $table->integer('percent_growth_orders',false)->nullable()->default(null)->after('no_of_orders');
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
            $table->dropColumn(['percent_growth_orders']);
            $table->dropColumn(['percent_growth_active_retail_customers']);
            $table->dropColumn(['percent_growth_active_duds']);
            $table->dropColumn(['percent_growth_active_dealers']);
            $table->dropColumn(['percent_growth_retail_customers']);
            $table->dropColumn(['percent_growth_duds']);
            $table->dropColumn(['percent_growth_dealers']);
        });
    }
}
