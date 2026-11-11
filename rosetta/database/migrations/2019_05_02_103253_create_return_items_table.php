<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReturnItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('returnable_id', false)->nullable();
            $table->string('returnable_type')->nullable();
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('distributor_id',false)->nullable()->default(null);
            $table->string('customer_type');
            $table->integer('customer_id')->nullable();
            $table->string('product_id');
            $table->double('unit_price',12,2);
            $table->double('discount',8,2)->default(0);
            $table->string('rate_type');
            $table->double('quantity', 8,2)->default(0);
            $table->double('amount',12,2);
            $table->string('batch_no');
            $table->string('status');
            $table->integer('authorised_by',false)->nullable()->default(null);
            $table->integer('authorisation_date',false)->nullable()->default(null);
            $table->text('reject_reason')->nullable()->default(null);
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
        Schema::dropIfExists('return_items');
    }
}
