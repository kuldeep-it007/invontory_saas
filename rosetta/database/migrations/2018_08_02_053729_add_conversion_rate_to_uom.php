<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddConversionRateToUom extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('uom_mappings', function (Blueprint $table) {
            $table->double('conversion_rate')->after('to')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('uom_mappings', function (Blueprint $table) {
            $table->dropColumn('conversion_rate');
        });
    }
}
