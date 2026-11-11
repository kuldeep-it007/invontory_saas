<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterAdvanceItemsTableAddNavisionPONum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_items', function(Blueprint $table)
        {
            $table->string('nav_intransit_po',false)->nullable()->default(null)->after('company_id');  
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_items', function (Blueprint $table){
            $table->dropColumn(['nav_intransit_po']);
        });
    }
}
