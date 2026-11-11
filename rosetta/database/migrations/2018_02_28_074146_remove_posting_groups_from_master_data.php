<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemovePostingGroupsFromMasterData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('master_data')->where('type', '=', 'inv_posting_grp')->delete();
        DB::table('master_data')->where('type', '=', 'gen_prod_posting_grp')->delete();
        DB::table('master_data')->where('type', '=', 'vat_prod_posting_grp')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
