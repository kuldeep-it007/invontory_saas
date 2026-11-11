<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMasterReportsTableAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_reports', function (Blueprint $table) {
            $table->integer('no_of_duds_ordering_cur_mon',false)->nullable()->default(null)->after('percent_growth_active_duds');
            $table->integer('percent_duds_ordering_cur_mon',false)->nullable()->default(null)->after('no_of_duds_ordering_cur_mon');
            $table->integer('no_of_dealers_ordering_cur_mon',false)->nullable()->default(null)->after('percent_growth_active_dealers');
            $table->integer('percent_dealers_ordering_cur_mon',false)->nullable()->default(null)->after('no_of_dealers_ordering_cur_mon');
            $table->integer('no_of_retail_customers_ordering_cur_mon',false)->nullable()->default(null)->after('percent_growth_active_retail_customers');
            $table->integer('percent_retail_customers_ordering_cur_mon',false)->nullable()->default(null)->after('no_of_retail_customers_ordering_cur_mon');
            $table->integer('no_of_newly_added_duds',false)->nullable()->default(null)->after('percent_dealers_ordering_cur_mon');
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
            $table->dropColumn(['no_of_newly_added_duds']);
            $table->dropColumn(['percent_retail_customers_ordering_cur_mon']);
            $table->dropColumn(['no_of_retail_customers_ordering_cur_mon']);
            $table->dropColumn(['percent_dealers_ordering_cur_mon']);
            $table->dropColumn(['no_of_dealers_ordering_cur_mon']);
            $table->dropColumn(['percent_duds_ordering_cur_mon']);
            $table->dropColumn(['no_of_duds_ordering_cur_mon']);
        });
    }
}
