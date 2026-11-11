<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableBackOrderLineItemsAddDiscount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('back_order_line_items', function (Blueprint $table) {
            $table->float('discount')->nullable()->default(0)->after('batch_no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('back_order_line_items', function (Blueprint $table){
            $table->dropColumn(['discount']);
        });
    }
}
