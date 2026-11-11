<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableAdvanceItemsAddDistributorId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advance_items', function (Blueprint $table) {
            $table->integer('distributor_id',false)->nullable()->default(null)->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advance_items', function (Blueprint $table) {
            $table->dropColumn(['distributor_id']);
        });
    }
}
