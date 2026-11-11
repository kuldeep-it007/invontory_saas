<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePurchaseOrdersTableAddInvoiceNumber extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
          Schema::table('purchase_orders', function(Blueprint $table)
        {
            $table->string('nav_invoice_number')->nullable()->default(null)->after('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table){
            $table->dropColumn(['nav_invoice_number']);
        });
    }
}
