<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPermissionsToListOnlineDiscounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageOnlineDiscountsGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'online_discounts',
            "display_name" => 'Manage Online Discounts',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        $listOnlineDiscountsPermission = DB::table('permissions')->insertGetId([
            "name" => "viewOnlineDiscounts",
            "display_name" => "View Online Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageOnlineDiscountsGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $listOnlineDiscountsPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $listOnlineDiscountsPermission,
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
