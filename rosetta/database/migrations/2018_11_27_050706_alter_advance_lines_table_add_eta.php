<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAdvanceLinesTableAddEta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_lines', function (Blueprint $table) {
            $table->integer('eta',false)->nullable()->default(null)->after('discount');
            $table->integer('advance_item_id', false)->nullable()->after('eta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_lines', function (Blueprint $table){
            $table->dropColumn(['eta']);
            $table->dropColumn(['advance_item_id']);
        });
    }
}
