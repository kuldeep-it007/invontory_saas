<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompanyIdToBlockedItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blocked_items', function (Blueprint $table) {
            $table->integer('company_id',false)->nullable()->default(null)->after('blockable_type');
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
            $table->dropColumn(['company_id']);
        });
    }
}
