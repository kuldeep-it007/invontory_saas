<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserRolePermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $userPermissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'users',
            "display_name" => 'Manage users',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $permissionRegionId = DB::table('permissions')->insertGetId([
            "name" => "customers",
            "display_name" => "Manage Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $userPermissionGroupID,

        ]);


        $viewUserPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewUser",
            "display_name" => "View user",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $userPermissionGroupID,

        ]);
        $updateUserPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateUser",
            "display_name" => "update User",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $userPermissionGroupID,

        ]);
        $createUserPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createUser",
            "display_name" => "create User",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $userPermissionGroupID,

        ]);
        $deleteUserPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteUser",
            "display_name" => "delete User",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $userPermissionGroupID,

        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $dealerAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','dealerAdmin')->first()->id;
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
   
         DB::table('permission_role')->insert([
                [
                    "permission_id" => $viewUserPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $updateUserPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $createUserPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $deleteUserPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewUserPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $updateUserPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $createUserPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $deleteUserPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $viewUserPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $updateUserPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $createUserPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $deleteUserPermissionId,
                    "role_id"       => $dealerAdminRoleId
                ],
                [
                    "permission_id" => $viewUserPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $updateUserPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $createUserPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $deleteUserPermissionId,
                    "role_id"       => $distributorAdminRoleId
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
