<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterDealersTableAddCreditLimitOverdueHold extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->double('credit_limit', 12,2)->default(0)->after('distributor_id');
            $table->integer('overdue_hold', false)->nullable()->default(null)->after('credit_limit');
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
            $table->dropColumn(['credit_limit']);
            $table->dropColumn(['overdue_hold']);
        });
    }
}
