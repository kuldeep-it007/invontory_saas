<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPermissionToDeleteOnlineDiscount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageOnlineDiscountsGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','online_discounts')->first()->id;
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        $deleteOnlineDiscountsPermission = DB::table('permissions')->insertGetId([
            "name" => "deleteOnlineDiscount",
            "display_name" => "Delete Online Discount",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageOnlineDiscountsGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $deleteOnlineDiscountsPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteOnlineDiscountsPermission,
                "role_id"       => $companyAdminRoleId
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
