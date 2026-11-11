<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdvanceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advance_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('advanceable_id', false)->nullable();
            $table->string('advanceable_type')->nullable();
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('product_id');
            $table->double('order_quantity',8,2);
            $table->double('unit_price',12,2);
            $table->double('discount',8,2)->default(0);
            $table->double('discounted_price',12,2);
            $table->double('total_price',12,2);
            $table->string('rate_type');
            $table->integer('eta',false)->nullable()->default(null);
            $table->integer('expires_at',false)->nullable()->default(null);
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
        Schema::dropIfExists('advance_items');
    }
}
