<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPermissionsToViewBlockedItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $blockedItemsGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'blocked_items',
            "display_name" => 'Manage blocked items',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        
        $blockedItemPermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageBlockedItems",
            "display_name" => "Manage blocked items",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $blockedItemsGroupID,
        ]);


        DB::table('permission_role')->insert([
            [
                "permission_id" => $blockedItemPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $blockedItemPermissionId,
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
