<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableDistributorsAddSuperStockistId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distributors', function(Blueprint $table)
        {
            $table->integer('super_stockist_id',false)->nullable()->default(null)->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('distributors', function (Blueprint $table){
            $table->dropColumn(['super_stockist_id']);
        });
    }
}
