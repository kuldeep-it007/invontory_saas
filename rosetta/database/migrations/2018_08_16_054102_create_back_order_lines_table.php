<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBackOrderLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('back_order_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('back_order_id',false);
            $table->string('product_id');
            $table->float('required_quantity')->nullable()->default(0);
            $table->float('ordered_quantity')->default(0);
            $table->float('unit_price')->nullable();
            $table->float('discount')->nullable()->default(0);
            $table->string('status')->nullable()->default(null);
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
        Schema::dropIfExists('back_order_lines');
    }
}
