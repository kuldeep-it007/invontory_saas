<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMarkupRemoveStateCityPostcode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('markup', function (Blueprint $table){
            $table->dropColumn(['state_code']);
            $table->dropColumn(['city_code']);
            $table->dropColumn(['post_code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('markup', function (Blueprint $table) {
            $table->string('state_code')->nullable()->default(null);
            $table->string('city_code')->nullable()->default(null);
            $table->string('post_code')->nullable()->default(null);
        });
    }
}
