<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsReadToBlanketOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->tinyInteger('is_read')
                ->default(0)
                ->comment('0=unread, 1=read')
                ->after('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->dropColumn('is_read');
        });
    }
}
