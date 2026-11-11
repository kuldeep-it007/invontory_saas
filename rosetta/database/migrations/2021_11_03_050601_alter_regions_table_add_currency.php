<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRegionsTableAddCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('currency_major')->nullable()->default(null)->after('tax_information');  
            $table->string('currency_minor')->nullable()->default(null)->after('currency_major');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('regions', function (Blueprint $table){
            $table->dropColumn(['currency_symbol']);
            $table->dropColumn(['currency_minor']);
        });
    }
}
