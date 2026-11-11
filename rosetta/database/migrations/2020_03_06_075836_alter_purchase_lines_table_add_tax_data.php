<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPurchaseLinesTableAddTaxData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->string('tax_type_code')->nullable()->default(null)->after('amount');
            $table->double('tax_percent',8,2)->default(0)->after('tax_type_code');
            $table->double('tax_amount',12,2)->default(0)->after('tax_percent');
            $table->double('net_amount',12,2)->default(0)->after('tax_amount');
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
            $table->dropColumn(['tax_type_code']);
            $table->dropColumn(['tax_percent']);
            $table->dropColumn(['tax_amount']);
            $table->dropColumn(['net_amount']);
        });
    }
}
