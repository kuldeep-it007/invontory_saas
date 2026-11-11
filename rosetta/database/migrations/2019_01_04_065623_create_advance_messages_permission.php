<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvanceMessagesPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','advance_orders')->first()->id;

        $viewAdvanceMessagesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewAdvanceMessages",
            "display_name" => "View Advance Messages",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $sendAdvanceMessagesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "sendAdvanceMessages",
            "display_name" => "Send Advance Messages",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        $dealerAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','dealerAdmin')->first()->id;

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewAdvanceMessagesPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $viewAdvanceMessagesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewAdvanceMessagesPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $viewAdvanceMessagesPermissionId,
                "role_id"       => $dealerAdminRoleId
            ],
            [
                "permission_id" => $sendAdvanceMessagesPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $sendAdvanceMessagesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $sendAdvanceMessagesPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $sendAdvanceMessagesPermissionId,
                "role_id"       => $dealerAdminRoleId
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
