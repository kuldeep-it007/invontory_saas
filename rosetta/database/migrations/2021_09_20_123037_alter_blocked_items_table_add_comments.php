<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBlockedItemsTableAddComments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blocked_items', function (Blueprint $table) {
            $table->text('comment')->nullable()->default(null)->after('rate_type');
            $table->string('UUID')->after('comment');

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
            $table->dropColumn(['UUID']);
            $table->dropColumn(['comment']);
        });
    }
}
