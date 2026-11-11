<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('UUID')->nullable()->default(null);
            $table->string('transactionable_type');
            $table->integer('transactionable_id',false);
            $table->integer('created_by',false);
            $table->integer('blanket_order_id',false)->nullable()->default(null);
            $table->string('external_reference')->nullable()->default(null);
            $table->string('gateway')->nullable()->default(null);
            $table->string('status')->nullable()->default(null);
            $table->integer('company_id',false);
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
        Schema::dropIfExists('transactions');
    }
}
