<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInMultiplesToCollection extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('collection_codes', function (Blueprint $table){
            $table->float('in_multiples')->nullable()->default(null)->after('description');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('category_codes', function (Blueprint $table){
            $table->dropColumn(['in_multiples']);
        });
    }
}
