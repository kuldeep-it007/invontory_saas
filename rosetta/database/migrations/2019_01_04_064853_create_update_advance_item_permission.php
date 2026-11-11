<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUpdateAdvanceItemPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','advance_orders')->first()->id;

        $updateAdvanceItemsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateAdvanceItems",
            "display_name" => "Update Advance Items",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        DB::table('permission_role')->insert([
            [
                "permission_id" => $updateAdvanceItemsPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $updateAdvanceItemsPermissionId,
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
