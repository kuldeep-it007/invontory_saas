<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvanceOrderPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'advance_orders',
            "display_name" => 'Manage Advance Orders',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);
        $viewAdvanceOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewAdvanceOrders",
            "display_name" => "View Advance Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $createAdvanceOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createAdvanceOrders",
            "display_name" => "Create Advance Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $updateAdvanceOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateAdvanceOrders",
            "display_name" => "Update Advance Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $updateAdvanceLinesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateAdvanceLines",
            "display_name" => "Update Advance Lines",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $deleteAdvanceLinesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteAdvanceLines",
            "display_name" => "Delete Advance Lines",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $deleteAdvanceItemsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteAdvanceItems",
            "display_name" => "Delete Advance Items",
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
                    "permission_id" => $viewAdvanceOrdersPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewAdvanceOrdersPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $viewAdvanceOrdersPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $viewAdvanceOrdersPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $createAdvanceOrdersPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $createAdvanceOrdersPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $updateAdvanceOrdersPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $updateAdvanceOrdersPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deleteAdvanceItemsPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $deleteAdvanceItemsPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $updateAdvanceLinesPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $updateAdvanceLinesPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deleteAdvanceLinesPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $deleteAdvanceLinesPermissionId,
                    "role_id"       => $companyAdminRoleId
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
