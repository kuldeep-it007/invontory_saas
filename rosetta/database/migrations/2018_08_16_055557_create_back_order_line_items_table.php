<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBackOrderLineItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('back_order_line_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('back_order_line_id',false);
            $table->integer('purchase_order_id', false)->nullable()->default(null);
            $table->float('quantity')->nullable()->default(0);
            $table->string('batch_no')->nullable();
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
        Schema::dropIfExists('back_order_line_items');
    }
}
