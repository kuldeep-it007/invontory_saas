<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterBlanketOrdersTableAddSourceOnBehalf extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blanket_orders', function (Blueprint $table) {   
            $table->string('source')->nullable()->default(null)->after('order_status');
            $table->string('on_behalf')->nullable()->default(null)->after('source');
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
            $table->dropColumn(['on_behalf']);
            $table->dropColumn(['source']);
        });
    }
}
