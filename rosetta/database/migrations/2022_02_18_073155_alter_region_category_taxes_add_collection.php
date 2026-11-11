<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRegionCategoryTaxesAddCollection extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('region_category_taxes', function (Blueprint $table) {
            $table->string('collection_code')->nullable()->default(null)->after('category_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('region_category_taxes', function (Blueprint $table){
            $table->dropColumn(['collection_code']);
        });
    }
}
