<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_address_id']);
            $table->string('order_number')->change();
            $table->string('navision_order_number')->change();
            $table->string('special_request')->change()->nullable();
            $table->string('invoice_id')->change()->nullable();
            $table->string('payment_id')->change()->nullable();
            $table->string('fulfilled_by')->change()->nullable();
            $table->string('error_info')->change()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
