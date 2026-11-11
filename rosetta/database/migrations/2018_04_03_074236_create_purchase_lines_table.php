<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchaseLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id',false);
            $table->integer('product_id',false);
            $table->float('unit_price');
            $table->string('unit_of_measure');
            $table->string('rate_type');
            $table->float('amount');
            $table->string('batch_no');
            $table->string('status');
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_lines');
    }
}
