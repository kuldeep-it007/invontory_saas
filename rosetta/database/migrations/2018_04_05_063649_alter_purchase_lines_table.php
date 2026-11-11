<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPurchaseLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['status']);
            $table->dropColumn(['order_id']);
            $table->string('product_id')->change();
            $table->string('batch_no')->change()->nullable();
            $table->integer('purchase_order_id')->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_lines', function (Blueprint $table){
            $table->dropColumn(['purchase_order_id']);
        });
    }
}
