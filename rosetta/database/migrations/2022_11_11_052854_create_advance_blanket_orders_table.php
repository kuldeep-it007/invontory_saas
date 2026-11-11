<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvanceBlanketOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advance_blanket_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('orderable_id', false)->nullable();
            $table->string('orderable_type')->nullable();
            $table->string('order_number')->nullable();
            $table->string('order_status')->default('Open');
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('distributor_id',false)->nullable()->default(null);
            $table->integer('stock_point_id',false)->nullable()->default(null);
            $table->integer('related_ao_id',false)->nullable()->default(null);
            $table->integer('end_customer_id',false)->nullable()->default(null);
            $table->text('cancelation_reason')->nullable()->default(null);
            $table->integer('canceled_by',false)->nullable()->default(null);
            $table->boolean('shipped')->default(false);  
            $table->string('invoice_id')->nullable()->default(null);
            $table->string('ros_invoice_number')->nullable()->default(null);
            $table->boolean('printed')->default(false);  
            $table->string('source')->nullable()->default(null);
            $table->string('on_behalf')->nullable()->default(null);
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
        Schema::dropIfExists('advance_blanket_orders');
    }
}
