<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterDealersTableAddAddressAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('city_code')->nullable()->default(null);
            $table->string('state_code')->nullable()->default(null);
            $table->string('post_code')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dealers', function (Blueprint $table){
            $table->dropColumn(['post_code']);
            $table->dropColumn(['state_code']);
            $table->dropColumn(['city_code']);
        });
    }
}
