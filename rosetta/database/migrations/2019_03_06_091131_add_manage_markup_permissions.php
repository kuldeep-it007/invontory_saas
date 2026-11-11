<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManageMarkupPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $markupGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'markup',
            "display_name" => 'Manage Markup',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $listMarkupGroupIDPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewMarkup",
            "display_name" => "View Markup",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $markupGroupID,
        ]);
        $manageMarkupGroupIDPermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageMarkup",
            "display_name" => "Manage Markup",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $markupGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $listMarkupGroupIDPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $manageMarkupGroupIDPermissionId,
                "role_id"       => $distributorAdminRoleId
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
