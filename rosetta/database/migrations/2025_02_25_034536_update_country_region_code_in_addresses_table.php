<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCountryRegionCodeInAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('country_region_code', 255)
                  ->charset('utf8mb4')
                  ->collation('utf8mb4_unicode_ci')
                  ->nullable()
                  ->default(null)
                  ->change();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Reverse the changes (adjust this according to your previous schema)
            $table->string('country_region_code')->change();
        });
    }
}
