<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDistributorsTableAddContactPhoneEmail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distributors', function(Blueprint $table)
        {
            $table->string('contact')->nullable()->default(null)->after('name');
            $table->string('phone')->nullable()->default(null)->after('contact');
            $table->string('email')->nullable()->default(null)->after('phone');
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
            $table->dropColumn(['email']);
            $table->dropColumn(['phone']);
            $table->dropColumn(['contact']);
        });
    }
}
