<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlockedItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blocked_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('blockable_id', false)->nullable();
            $table->string('blockable_type')->nullable();
            $table->string('product_id');
            $table->string('batch_no');
            $table->float('quantity');
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
        Schema::dropIfExists('blocked_items');
    }
}
