<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddManageProductsPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $productsPermissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','products')->first()->id;
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        
        $manageProductsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageProducts",
            "display_name" => "Add/Edit Products",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $productsPermissionGroupID,
        ]);


        DB::table('permission_role')->insert([
            [
                "permission_id" => $manageProductsPermissionId,
                "role_id"       => $superAdminRoleId
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
