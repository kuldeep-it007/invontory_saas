<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCustomerPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'customers',
            "display_name" => 'Manage Customers',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $permissionRegionId = DB::table('permissions')->insertGetId([
            "name" => "customers",
            "display_name" => "Manage Customers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,

        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;

        DB::table('permission_role')->insert([
                [
                    "permission_id" => $permissionRegionId,
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
