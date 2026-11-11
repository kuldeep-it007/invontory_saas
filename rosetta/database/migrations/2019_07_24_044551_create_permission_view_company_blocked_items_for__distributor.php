<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionViewCompanyBlockedItemsForDistributor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $blockedItemsGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','blocked_items')->first()->id;

        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $viewblockedItemPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewDudBlockedItems",
            "display_name" => "View DUD blocked items",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $blockedItemsGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewblockedItemPermissionId,
                "role_id"       => $distributorAdminRoleId
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
