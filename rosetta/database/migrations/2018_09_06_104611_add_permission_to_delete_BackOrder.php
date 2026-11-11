<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPermissionToDeleteBackOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','back_orders')->first()->id;

        $deleteBackOrderPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteBackOrder",
            "display_name" => "Delete Back Order",
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
                "permission_id" => $deleteBackOrderPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteBackOrderPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteBackOrderPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $deleteBackOrderPermissionId,
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
