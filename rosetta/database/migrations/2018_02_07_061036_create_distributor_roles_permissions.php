<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDistributorRolesPermissions extends Migration
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

        $viewDealerPermissionId = DB::table('permissions')->select(['id'])->where('name','=','viewDealers')->first()->id;
        $updateDealerPermissionId = DB::table('permissions')->select(['id'])->where('name','=','updateDealers')->first()->id;
        $createDealerPermissionId = DB::table('permissions')->select(['id'])->where('name','=','createDealers')->first()->id;
        $deleteDealerPermissionId = DB::table('permissions')->select(['id'])->where('name','=','deleteDealers')->first()->id;

        $distributorAdminRoleId = DB::table('roles')->insertGetId([
            "name" => "distributorAdmin",
            "display_name" => "Distributor Admin",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        $distributorSalesRepRoleId = DB::table('roles')->insertGetId([
            "name" => "distributorSalesRep",
            "display_name" => "Distributor Sales Representative",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);


        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'distributors',
            "display_name" => 'Manage Distributors',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $createPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createDistributors",
            "display_name" => "Create Distributors",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $updatePermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateDistributors",
            "display_name" => "Update Distributors",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $deletePermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteDistributors",
            "display_name" => "Delete Distributors",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $viewPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewDistributors",
            "display_name" => "View Distributors",
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
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $viewDealerPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $updateDealerPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $createDealerPermissionId,
                    "role_id"       => $distributorAdminRoleId
                ],
                [
                    "permission_id" => $deleteDealerPermissionId,
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
