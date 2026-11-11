<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableBackOrderLinesAddPricingAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('back_order_lines', function (Blueprint $table) {
            $table->boolean('confirmed')->default(false)->after('status');
            $table->float('discounted_price')->after('unit_price');
            $table->float('total_price')->after('discounted_price');
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
            $table->dropColumn(['total_price']);
            $table->dropColumn(['discounted_price']);
            $table->dropColumn(['confirmed']);
        });
    }
}
