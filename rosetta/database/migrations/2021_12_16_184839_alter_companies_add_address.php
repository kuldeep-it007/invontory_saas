<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCompaniesAddAddress extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        Schema::table('companies', function(Blueprint $table)
        {
            $table->string('contact')->nullable()->default(null);
            $table->string('phone_no')->nullable()->default(null);
            $table->string('email')->nullable()->default(null);
            $table->string('website')->nullable()->default(null);
            $table->string('address')->nullable()->default(null);
            $table->string('address_2')->nullable()->default(null);
            $table->string('city')->nullable()->default(null);
            $table->string('state')->nullable()->default(null);
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
         Schema::table('companies', function (Blueprint $table){
            $table->dropColumn(['post_code']);
            $table->dropColumn(['state']);
            $table->dropColumn(['city']);
            $table->dropColumn(['address_2']);
            $table->dropColumn(['address']);
            $table->dropColumn(['website']);
            $table->dropColumn(['email']);
            $table->dropColumn(['phone_no']);
            $table->dropColumn(['contact']);
        });
    }
}
