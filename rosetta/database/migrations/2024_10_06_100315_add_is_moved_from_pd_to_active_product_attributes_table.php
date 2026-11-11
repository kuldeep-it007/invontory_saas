<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsMovedFromPdToActiveProductAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('active_product_attributes', function (Blueprint $table) {
            $table->integer('is_moved_from_pd')->default(0); // Change 'some_column' to the appropriate column
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('active_product_attributes', function (Blueprint $table) {
            $table->dropColumn('is_moved_from_pd');
        });
    }
}
