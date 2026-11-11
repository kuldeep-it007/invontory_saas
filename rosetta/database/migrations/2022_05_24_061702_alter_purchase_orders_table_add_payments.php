<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterPurchaseOrdersTableAddPayments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->double('remaining_amount', 12,2)->default(0)->after('Amount'); 
            $table->double('paid_amount', 12,2)->default(0)->after('remaining_amount'); 
            $table->string('payment_status')->default('Pending')->after('paid_amount');      
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
            $table->dropColumn(['payment_status']);
            $table->dropColumn(['paid_amount']);
            $table->dropColumn(['remaining_amount']);
        });
    }
}
