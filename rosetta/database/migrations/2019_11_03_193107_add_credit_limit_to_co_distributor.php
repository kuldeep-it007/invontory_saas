<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreditLimitToCoDistributor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('co_distributors', function (Blueprint $table) {
            $table->double('credit_limit',12,2)->nullable()->default(0.0)->after('co_distributor_id');
            $table->double('overdue_balance',12,2)->nullable()->default(0.0)->after('co_distributor_id');
            $table->integer('overdue_hold_days',false)->nullable()->default(null)->after('co_distributor_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('co_distributors', function (Blueprint $table){
            $table->dropColumn(['credit_limit']);
            $table->dropColumn(['overdue_balance']);
            $table->dropColumn(['overdue_hold_days']);
        });
    }
}
