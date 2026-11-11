<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterColoursTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('colours', function (Blueprint $table) {
            $table->dropColumn(['id']);
            $table->dropColumn(['description']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('colours', function (Blueprint $table){
            $table->string('id');
            $table->string('description')->after('collection_code');
        });
    }
}
