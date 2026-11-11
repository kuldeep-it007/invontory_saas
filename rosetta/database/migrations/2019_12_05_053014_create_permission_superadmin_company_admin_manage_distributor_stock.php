<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionSuperadminCompanyAdminManageDistributorStock extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         $manageStockGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','manage_stock')->first()->id;

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        
        $addStockPermissionId = DB::table('permissions')->select(['id'])->where('name','=','addStock')->first()->id;

        $updateStockPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateStock",
            "display_name" => "Update Stock",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageStockGroupID,
        ]);

        $deleteStockPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteStock",
            "display_name" => "Delete Stock",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageStockGroupID,
        ]);

        
        DB::table('permission_role')->insert([
            [
                "permission_id" => $addStockPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addStockPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $updateStockPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $updateStockPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteStockPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteStockPermissionId,
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
