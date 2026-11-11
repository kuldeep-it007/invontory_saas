<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTempDealersTableAddStatusRejectionReason extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('temp_dealers', function (Blueprint $table) {
            $table->string('status')->after('text');
            $table->text('rejection_reason')->nullable()->default(null)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('temp_dealers', function (Blueprint $table){
            $table->dropColumn(['status']);
            $table->dropColumn(['rejection_reason']);
        });
    }
}
