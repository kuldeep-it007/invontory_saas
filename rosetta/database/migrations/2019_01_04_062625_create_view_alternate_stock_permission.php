<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViewAlternateStockPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','companyStock')->first()->id;

        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        $dealerAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','dealerAdmin')->first()->id;
        
        $viewAlternateStockPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewAlternateStock",
            "display_name" => "View Alternate Stock",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewAlternateStockPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $viewAlternateStockPermissionId,
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
