<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('addressable_id', false)->nullable();
            $table->string('addressable_type')->nullable();
            $table->string('nav_address_code')->nullable();
            $table->string('address');
            $table->string('address_2')->nullable()->default(null);;
            $table->string('post_code');
            $table->string('city');
            $table->string('country_region_code');
            $table->string('state');
            $table->string('contact')->nullable();
            $table->string('name')->nullable();
            $table->string('phone_no')->nullable();
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
        Schema::dropIfExists('addresses');
    }
}
