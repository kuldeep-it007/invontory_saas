<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseLinesPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','purchase_orders')->first()->id;

        $updatePurchaseLinesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updatePurchaseLines",
            "display_name" => "Update Purchase Lines",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $deletePurchaseLinesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deletePurchaseLines",
            "display_name" => "Delete Purchase Lines",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        

        DB::table('permission_role')->insert([
            [
                "permission_id" => $updatePurchaseLinesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $updatePurchaseLinesPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deletePurchaseLinesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deletePurchaseLinesPermissionId,
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
