<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionToManageReturns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageReturnsGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'manage_returns',
            "display_name" => 'Manage Returns',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $manageReturnsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageReturns",
            "display_name" => "Manage Returns",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageReturnsGroupID,
        ]);


        DB::table('permission_role')->insert([
            [
                "permission_id" => $manageReturnsPermissionId,
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
