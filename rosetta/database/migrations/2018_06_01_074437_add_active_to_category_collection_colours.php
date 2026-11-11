<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddActiveToCategoryCollectionColours extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('category_codes', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('cl_percent');
        });

        Schema::table('collection_codes', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('description');
        });
        Schema::table('colours', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('collection_code');
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
            $table->dropColumn(['active']);
        });

        Schema::table('collection_codes', function (Blueprint $table){
            $table->dropColumn(['active']);
        });
        Schema::table('colours', function (Blueprint $table){
            $table->dropColumn(['active']);
        });
    }
}
