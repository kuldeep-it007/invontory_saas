<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('paymentable_id', false)->nullable();
            $table->string('paymentable_type')->nullable();
            $table->string('customer_type');
            $table->integer('customer_id')->nullable();
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('distributor_id',false)->nullable()->default(null);
            $table->double('amount', 12, 2);
            $table->string('payment_mode')->nullable();
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
        Schema::dropIfExists('payments');
    }
}
