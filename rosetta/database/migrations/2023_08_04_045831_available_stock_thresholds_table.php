<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AvailableStockThresholdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('available_stock_thresholds', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id',false);
            $table->integer('stock_point_id',false);
            $table->integer('company_id',false);
            $table->string('tenant')->nullable()->default(null);
            $table->float('threshold_qty')->default(0);
            $table->float('available_qty')->default(0);
            $table->float('reorder_qty')->default(0);
            $table->double('lc', 8,2)->default(0);
            $table->string('uom')->nullable()->default('SFT');
            $table->double('value', 8,2)->default(0);
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
            $table->integer('deleted_at',false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('available_stock_thresholds');
    }
}
