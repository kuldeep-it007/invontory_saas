<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInMultiplesToColours extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('colours', function (Blueprint $table){
            $table->float('in_multiples')->nullable()->default(null)->after('collection_code');

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
            $table->dropColumn(['in_multiples']);
        });
    }
}
