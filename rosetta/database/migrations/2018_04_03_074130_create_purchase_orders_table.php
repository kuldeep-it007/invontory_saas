<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_number',false);
            $table->integer('navision_order_number',false);
            $table->float('Amount');
            $table->string('status');
            $table->string('special_request');
            $table->string('shipping_method');
            $table->integer('shipping_address_id',false);
            $table->integer('invoice_id',false);
            $table->integer('payment_id',false);
            $table->string('fulfilled_by');
            $table->string('error_info');
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
        Schema::dropIfExists('purchase_orders');
    }
}
