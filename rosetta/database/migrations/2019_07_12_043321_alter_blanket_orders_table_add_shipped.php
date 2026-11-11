<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBlanketOrdersTableAddShipped extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->boolean('shipped')->default(false)->after('status');  
            $table->string('invoice_id')->nullable()->default(null)->after('distributor_id');
            $table->dropColumn(['status']);         
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
            $table->dropColumn(['shipped']);
            $table->dropColumn(['invoice_id']);
        });
    }
}
