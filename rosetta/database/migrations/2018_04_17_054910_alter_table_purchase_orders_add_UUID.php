<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePurchaseOrdersAddUUID extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->integer('orderable_id', false)->nullable()->after('id');
            $table->string('orderable_type')->nullable()->after('orderable_id');
            $table->string('UUID')->after('navision_order_number');
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
            $table->dropColumn(['UUID']);
            $table->dropColumn(['orderable_type']);
            $table->dropColumn(['orderable_id']);
        });
    }
}
