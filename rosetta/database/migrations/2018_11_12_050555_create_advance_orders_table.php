<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdvanceOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advance_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('orderable_id', false)->nullable();
            $table->string('orderable_type')->nullable();
            $table->string('order_number');
            $table->string('navision_order_number');
            $table->text('nav_key');
            $table->string('UUID');
            $table->integer('company_id',false)->nullable()->default(null);
            $table->double('amount', 12, 2);
            $table->string('status');
            $table->string('special_request')->nullable();
            $table->text('cancelation_reason')->nullable()->default(null);
            $table->integer('canceled_by',false)->nullable()->default(null);
            $table->string('shipping_method');
            $table->string('invoice_id')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('fulfilled_by')->nullable();
            $table->string('error_info')->nullable();
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
        Schema::dropIfExists('advance_orders');
    }
}
