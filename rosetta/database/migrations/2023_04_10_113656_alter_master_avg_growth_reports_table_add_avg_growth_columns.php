<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMasterAvgGrowthReportsTableAddAvgGrowthColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_report_avg_growth', function (Blueprint $table) {
            $table->integer('avg_growth_in_active_duds',false)->nullable()->default(null)->after('avg_growth_no_of_dlrs');
            $table->integer('avg_growth_no_of_duds',false)->nullable()->default(null)->after('avg_growth_in_active_duds');
            $table->integer('avg_growth_in_active_rtc',false)->nullable()->default(null)->after('avg_growth_no_of_duds');
            $table->integer('avg_growth_no_of_rtc',false)->nullable()->default(null)->after('avg_growth_in_active_rtc');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_report_avg_growth', function (Blueprint $table){
            $table->dropColumn(['avg_growth_no_of_rtc']);
            $table->dropColumn(['avg_growth_in_active_rtc']);
            $table->dropColumn(['avg_growth_no_of_duds']);
            $table->dropColumn(['avg_growth_in_active_duds']);
        });
    }
}
