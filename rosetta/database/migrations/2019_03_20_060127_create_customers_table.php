<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customerable_id', false)->nullable();
            $table->string('customerable_type')->nullable();
            $table->integer('company_id',false);
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('phone_no')->nullable();
            $table->string('address');
            $table->string('address_2')->nullable()->default(null);
            $table->string('city');
            $table->string('state');
            $table->string('post_code');
            $table->string('country_region_code');
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
        Schema::dropIfExists('customers');
    }
}
