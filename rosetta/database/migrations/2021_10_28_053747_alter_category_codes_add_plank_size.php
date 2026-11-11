<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCategoryCodesAddPlankSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('category_codes', function (Blueprint $table){
           $table->double('plank_size', 8,4)->nullable()->default(null)->after('in_multiples');

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
            $table->dropColumn(['plank_size']);
        });
    }
}
