<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterDistributorsTableMakeAddressNonMandatory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('distributors', function (Blueprint $table) {
            $table->string('city_code')->nullable()->default(null)->change();
            $table->string('state_code')->nullable()->default(null)->change();
            $table->string('post_code')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
