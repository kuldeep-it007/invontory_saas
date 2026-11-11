<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBackOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('back_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('orderable_id', false);
            $table->string('orderable_type')->nullable();
            $table->string('order_number',false);
            $table->string('status')->nullable()->default(null);
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('created_by',false)->nullable();
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
        Schema::dropIfExists('back_orders');
    }
}
