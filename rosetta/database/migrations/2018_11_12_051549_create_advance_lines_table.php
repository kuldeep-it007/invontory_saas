<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdvanceLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advance_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nav_line_no')->nullable();
            $table->string('product_id');
            $table->integer('advance_order_id');
            $table->double('quantity',8,2);
            $table->double('shipped_quantity',8,2)->default(0);
            $table->double('received_quantity',8,2)->default(0);
            $table->string('rate_type');
            $table->double('amount',12,2);
            $table->string('batch_no')->nullable();
            $table->double('unit_price',12,2);
            $table->double('discount',8,2)->default(0);
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
        Schema::dropIfExists('advance_lines');
    }
}
