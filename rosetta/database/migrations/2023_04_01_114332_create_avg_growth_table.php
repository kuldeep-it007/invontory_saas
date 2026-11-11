<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvgGrowthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_report_avg_growth', function (Blueprint $table) {
            $table->increments('id');
            $table->string('month')->default(null)->nullable();
            $table->string('year')->default(null)->nullable();
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('avg_sales_growth',false)->nullable()->default(null);
            $table->integer('avg_growth_in_orders',false)->nullable()->default(null);
            $table->integer('avg_growth_in_active_dlrs',false)->nullable()->default(null);
            $table->integer('avg_growth_no_of_dlrs',false)->nullable()->default(null);
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
            $table->integer('deleted_at',false)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('avg_growth');
    }
}
