<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuperStockistsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('super_stockists', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nav_id');
            $table->string('name');
            $table->string('contact')->nullable()->default(null);
            $table->string('phone')->nullable()->default(null);
            $table->string('email')->nullable()->default(null);
            $table->string('gstin')->nullable()->default(null);
            $table->integer('company_id',false);
            $table->string('to_email')->nullable()->default(null);
            $table->string('cc_email')->nullable()->default(null);
            $table->smallInteger('payment_type')->default(1);
            $table->integer('invoice_series',false)->nullable()->default(0);
            $table->string('address')->nullable()->default(null);
            $table->string('address_2')->nullable()->default(null);
            $table->string('city_code')->nullable()->default(null);
            $table->string('state_code')->nullable()->default(null);
            $table->string('post_code')->nullable()->default(null);
            $table->integer('created_by',false)->nullable();
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
            $table->integer('deleted_at',false)->nullable();    
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('super_stockists');
    }
}
