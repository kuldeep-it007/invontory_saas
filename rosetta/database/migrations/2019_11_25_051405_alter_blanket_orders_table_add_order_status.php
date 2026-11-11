<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBlanketOrdersTableAddOrderStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->string('order_status')->default('Open')->after('shipped');        
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
            $table->dropColumn(['order_status']);
        });
    }
}
