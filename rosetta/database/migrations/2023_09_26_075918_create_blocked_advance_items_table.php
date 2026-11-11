<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlockedAdvanceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blocked_advance_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('blockable_id', false)->nullable();
            $table->string('blockable_type')->nullable();
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('transaction_id',false)->nullable()->default(null);
            $table->integer('distributor_id',false)->nullable()->default(null);
            $table->integer('product_id',false)->nullable()->default(null);
            $table->integer('stock_point_id',false)->nullable()->default(null);
            $table->string('nav_intransit_po',false)->nullable()->default(null); 
            $table->double('order_quantity', 8,2)->default(0);
            $table->double('unit_price', 8,2)->default(0);
            $table->double('discount', 8,2)->default(0);
            $table->double('onlineDiscount', 8,2)->default(0);
            $table->integer('eta',false)->nullable()->default(null);
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
            $table->integer('expires_at',false)->nullable()->default(null);
            $table->integer('cool_down_start_time',false)->nullable()->default(null);
            $table->integer('cool_down_end_time',false)->nullable()->default(null);
            $table->string('rate_type');
            $table->text('comment')->nullable()->default(null);
            $table->string('UUID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocked_advance_items');
    }
}
