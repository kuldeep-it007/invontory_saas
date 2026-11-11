<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableBlockedItemsAddCoolDownTill extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blocked_items', function (Blueprint $table) {
            $table->integer('cool_down_start_time',false)->nullable()->default(null)->after('expires_at');
            $table->integer('cool_down_end_time',false)->nullable()->default(null)->after('cool_down_start_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blocked_items', function (Blueprint $table){
            $table->dropColumn(['cool_down_end_time']);
            $table->dropColumn(['cool_down_start_time']);
        });
    }
}
