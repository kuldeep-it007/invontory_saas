<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBackOrderLinesTableAddEta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('back_order_lines', function (Blueprint $table) {
            $table->integer('eta',false)->nullable()->default(null)->after('confirmed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('back_order_lines', function (Blueprint $table){
            $table->dropColumn(['eta']);
        });
    }
}
