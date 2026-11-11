<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('reportable_id', false)->nullable();
            $table->string('reportable_type')->nullable();
            $table->string('month')->default(null)->nullable();
            $table->string('year')->default(null)->nullable();
            $table->integer('sales_amount',false)->nullable()->default(null);
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('no_of_distributors',false)->nullable()->default(null);
            $table->integer('no_of_dealers',false)->nullable()->default(null);
            $table->integer('no_of_active_dealers',false)->nullable()->default(null);
            $table->integer('no_of_orders',false)->nullable()->default(null);
            $table->integer('growth_percentage',false)->nullable()->default(null);
            $table->integer('target',false)->nullable()->default(null);
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
        Schema::dropIfExists('master_reports');
    }
}
