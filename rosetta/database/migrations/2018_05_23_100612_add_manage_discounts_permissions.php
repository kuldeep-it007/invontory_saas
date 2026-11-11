<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddManageDiscountsPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $discountsGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'discounts',
            "display_name" => 'Manage Discounts',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $listDiscountsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewDiscounts",
            "display_name" => "View Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $discountsGroupID,
        ]);
        $manageDiscountsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageDiscounts",
            "display_name" => "Manage Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $discountsGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $listDiscountsPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $manageDiscountsPermissionId,
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
