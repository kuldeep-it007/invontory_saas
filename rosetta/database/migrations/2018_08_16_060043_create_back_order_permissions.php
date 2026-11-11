<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBackOrderPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'back_orders',
            "display_name" => 'Manage Back Orders',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);
        $viewBackOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewBackOrders",
            "display_name" => "View Back Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $createBackOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createBackOrders",
            "display_name" => "Create Back Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $updateBackOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateBackOrders",
            "display_name" => "Update Back Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $deleteBackOrderLinesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteBackOrderLines",
            "display_name" => "Delete Back Order Lines",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $deleteBackOrderLineItemsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteBackOrderLineItems",
            "display_name" => "Delete Back Order Line Items",
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
                    "permission_id" => $viewBackOrdersPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewBackOrdersPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $viewBackOrdersPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $viewBackOrdersPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $createBackOrdersPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $createBackOrdersPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $createBackOrdersPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $createBackOrdersPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $updateBackOrdersPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $updateBackOrdersPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLinesPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLinesPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLinesPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLinesPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLineItemsPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLineItemsPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLineItemsPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $deleteBackOrderLineItemsPermissionId,
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
