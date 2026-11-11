<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAdvanceOrdersTableAddNavInvoiceNumberDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_orders', function(Blueprint $table)
        {
            $table->string('nav_invoice_number')->nullable()->default(null)->after('UUID');
            $table->integer('nav_invoice_date',false)->nullable()->default(null)->after('nav_invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_orders', function (Blueprint $table){
            $table->dropColumn(['nav_invoice_date']);
            $table->dropColumn(['nav_invoice_number']);
        });
    }
}
