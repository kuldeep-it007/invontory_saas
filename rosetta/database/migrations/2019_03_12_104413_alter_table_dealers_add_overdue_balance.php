<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableDealersAddOverdueBalance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->double('overdue_balance', 12,2)->default(0)->after('credit_limit');
            $table->dropColumn(['overdue_hold']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('dealers', function (Blueprint $table){
            $table->dropColumn(['overdue_balance']);
            $table->integer('overdue_hold', false)->nullable()->default(null)->after('credit_limit');
        });
    }
}
