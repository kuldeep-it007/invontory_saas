<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBlanketOrdersAddRosInvNum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
          Schema::table('blanket_orders', function(Blueprint $table)
        {
            $table->string('ros_invoice_number')->nullable()->default(null)->after('invoice_id');
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
            $table->dropColumn(['ros_invoice_number']);
        });
    }
}
