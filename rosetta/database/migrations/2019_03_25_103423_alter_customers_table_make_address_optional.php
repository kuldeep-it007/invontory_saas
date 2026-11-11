<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCustomersTableMakeAddressOptional extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->nullable()->default(null)->change();
            $table->string('city')->nullable()->default(null)->change();
            $table->string('state')->nullable()->default(null)->change();
            $table->string('post_code')->nullable()->default(null)->change();
            $table->string('country_region_code')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table){
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('post_code');
            $table->string('country_region_code');
        });
    }
}
