<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlanketOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blanket_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('orderable_id', false)->nullable();
            $table->string('orderable_type')->nullable();
            $table->string('order_number')->nullable();
            $table->string('status')->default('Open');
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('distributor_id',false)->nullable()->default(null);
            $table->integer('engineer_id',false)->nullable()->default(null);
            $table->integer('end_customer_id',false)->nullable()->default(null);
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
        Schema::dropIfExists('blanket_orders');
    }
}
