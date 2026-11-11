<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAdvanceLinesTableAddNavPurchaseOrderNumber extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_lines', function (Blueprint $table) {
            $table->string('nav_po_no')->nullable()->after('advance_item_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_lines', function (Blueprint $table){
            $table->dropColumn(['nav_po_no']);
        });
    }
}
