<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRetailCustomerRolesPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'retailCustomers',
            "display_name" => 'Manage Retail Customers',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $createPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createRetailCustomers",
            "display_name" => "Create Retail Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $updatePermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateRetailCustomers",
            "display_name" => "Update Retail Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $deletePermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteRetailCustomers",
            "display_name" => "Delete Retail Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $viewPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewRetailCustomers",
            "display_name" => "View Retail Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        DB::table('permission_role')->insert([
                [
                    "permission_id" => $viewPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $deletePermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $updatePermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $createPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deletePermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $updatePermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $createPermissionId,
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
