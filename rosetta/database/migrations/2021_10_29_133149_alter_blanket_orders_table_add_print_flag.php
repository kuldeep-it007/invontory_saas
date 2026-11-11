<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBlanketOrdersTableAddPrintFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->boolean('printed')->default(false)->after('shipped');         
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blanket_orders', function (Blueprint $table){
            $table->dropColumn(['printed']);
        });
    }
}
