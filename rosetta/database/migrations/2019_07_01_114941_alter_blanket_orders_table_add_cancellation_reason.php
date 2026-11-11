<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBlanketOrdersTableAddCancellationReason extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->text('cancelation_reason')->nullable()->default(null)->after('end_customer_id');
            $table->integer('canceled_by',false)->nullable()->default(null)->after('cancelation_reason');;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blanket_orders', function (Blueprint $table){
            $table->dropColumn(['cancelation_reason']);
            $table->dropColumn(['canceled_by']);
        });
    }
}
