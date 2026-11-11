<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPaymentTypeToDistributorDealer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->smallInteger('payment_type')->default(1)->after('company_id');
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->smallInteger('payment_type')->default(1)->after('distributor_id');
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
            $table->dropColumn(['payment_type']);
        });
        Schema::table('dealers', function (Blueprint $table){
            $table->dropColumn(['payment_type']);
        });
    }
}
