<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockPointPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'stockPoints',
            "display_name" => 'Manage Stock Points',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $companySalesRepId = DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id;
        
        $viewStockPointsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewStockPoints",
            "display_name" => "View Stock Points",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $addStockPointPermissionId = DB::table('permissions')->insertGetId([
            "name" => "addStockPoint",
            "display_name" => "Add Stock Point",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $viewStockPointCustomersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewStockPointCustomers",
            "display_name" => "View Stock Point Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $deleteStockPointCustomersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteStockPointCustomers",
            "display_name" => "Delete Stock Point Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $addStockPointCustomersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "addStockPointCustomers",
            "display_name" => "Add Stock Point Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewStockPointsPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewStockPointsPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $viewStockPointsPermissionId,
                "role_id"       => $companySalesRepId
            ],
            [
                "permission_id" => $addStockPointPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addStockPointPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $addStockPointPermissionId,
                "role_id"       => $companySalesRepId
            ],
            [
                "permission_id" => $viewStockPointCustomersPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewStockPointCustomersPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $viewStockPointCustomersPermissionId,
                "role_id"       => $companySalesRepId
            ],
            [
                "permission_id" => $deleteStockPointCustomersPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteStockPointCustomersPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteStockPointCustomersPermissionId,
                "role_id"       => $companySalesRepId
            ],
            [
                "permission_id" => $addStockPointCustomersPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addStockPointCustomersPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $addStockPointCustomersPermissionId,
                "role_id"       => $companySalesRepId
            ]
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
