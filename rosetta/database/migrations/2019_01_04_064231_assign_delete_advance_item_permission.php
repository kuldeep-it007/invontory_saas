<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AssignDeleteAdvanceItemPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $deleteAdvanceItemsPermissionId = DB::table('permissions')->select(['id'])->where('name','=','deleteAdvanceItems')->first()->id;

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        DB::table('permission_role')->insert([
            [
                "permission_id" => $deleteAdvanceItemsPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteAdvanceItemsPermissionId,
                "role_id"       => $superAdminRoleId
            ],
        ]);
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
