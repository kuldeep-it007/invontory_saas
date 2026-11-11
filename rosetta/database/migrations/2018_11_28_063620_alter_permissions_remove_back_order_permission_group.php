<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterPermissionsRemoveBackOrderPermissionGroup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        $BackOrderPermissionGroupId = DB::table('permission_groups')->select(['id'])->where('name','=','back_orders')->first()->id;

        DB::table('permission_groups')->where('id','=',$BackOrderPermissionGroupId)
            ->delete();
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
