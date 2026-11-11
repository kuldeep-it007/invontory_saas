<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserProfilePermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'profiles',
            "display_name" => 'Manage Profiles',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $manageProfilePermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageProfile",
            "display_name" => "Manage Profile",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $dealerAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','dealerAdmin')->first()->id;
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
   
         DB::table('permission_role')->insert([
            [
                "permission_id" => $manageProfilePermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $manageProfilePermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $manageProfilePermissionId,
                "role_id"       => $dealerAdminRoleId
            ],
            [
                "permission_id" => $manageProfilePermissionId,
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
