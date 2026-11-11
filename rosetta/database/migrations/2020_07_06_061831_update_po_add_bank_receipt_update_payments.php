<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePoAddBankReceiptUpdatePayments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('nav_bank_receipt_id',false)->nullable()->default(null)->after('nav_key');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('customer_type')->nullable()->default(null)->change();
            $table->string('payment_source')->nullable()->default(null)->after('payment_mode');
            $table->string('payment_ref')->nullable()->default(null)->after('payment_source');
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
            $table->dropColumn(['nav_bank_receipt_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_source');
            $table->dropColumn('payment_ref');
        });
    }
}
