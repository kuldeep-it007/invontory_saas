<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddImpersonatePermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $manageUsersGroup = DB::table('permission_groups')->select(['id'])->where('name','=','users')->first()->id;

        $impersonatePermission = DB::table('permissions')->insertGetId([
            "name" => "impersonate",
            "display_name" => "Impersonate other users",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageUsersGroup,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $impersonatePermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $impersonatePermission,
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
