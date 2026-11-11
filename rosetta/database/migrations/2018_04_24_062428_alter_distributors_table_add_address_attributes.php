<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterDistributorsTableAddAddressAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('city_code');
            $table->string('state_code');
            $table->string('post_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('distributors', function (Blueprint $table){
            $table->dropColumn(['post_code']);
            $table->dropColumn(['state_code']);
            $table->dropColumn(['city_code']);
        });
    }
}
