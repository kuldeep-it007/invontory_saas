<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerStockPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_stock_points', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customerable_id', false)->nullable();
            $table->string('customerable_type')->nullable();
            $table->integer('stock_point_id',false)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_stock_points');
    }
}
