<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPermissionsRemoveMarkupPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $viewMarkupPermissionId = DB::table('permissions')->select(['id'])->where('name','=','viewMarkup')->first()->id;

        DB::table('permissions')->where('id','=',$viewMarkupPermissionId)
            ->delete();

        $manageMarkupPermissionId = DB::table('permissions')->select(['id'])->where('name','=','manageMarkup')->first()->id;

        DB::table('permissions')->where('id','=',$manageMarkupPermissionId)
            ->delete();

        $MarkupPermissionGroupId = DB::table('permission_groups')->select(['id'])->where('name','=','markup')->first()->id;

        DB::table('permission_groups')->where('id','=',$MarkupPermissionGroupId)
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
