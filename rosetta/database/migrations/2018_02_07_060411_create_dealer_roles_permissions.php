<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDealerRolesPermissions extends Migration
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

        $dealerAdminRoleId = DB::table('roles')->insertGetId([
            "name" => "dealerAdmin",
            "display_name" => "Dealer Admin",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        $dealerSalesRepRoleId = DB::table('roles')->insertGetId([
            "name" => "dealerSalesRep",
            "display_name" => "Dealer Sales Representative",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);


        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'dealers',
            "display_name" => 'Manage Dealers',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $createPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createDealers",
            "display_name" => "Create Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $updatePermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateDealers",
            "display_name" => "Update Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $deletePermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteDealers",
            "display_name" => "Delete Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $viewPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewDealers",
            "display_name" => "View Dealers",
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
                [
                    "permission_id" => $viewPermissionId,
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
