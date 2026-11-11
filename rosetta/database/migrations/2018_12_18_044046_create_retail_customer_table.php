<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRetailCustomerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retail_customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nav_id');
            $table->string('name');
            $table->integer('company_id',false);
            $table->string('city_code');
            $table->string('state_code');
            $table->string('post_code');
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
        Schema::dropIfExists('retail_customer');
    }
}
