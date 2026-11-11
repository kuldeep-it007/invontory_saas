<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rosetta\Models\Role;

class CreateViewProductPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'products',
            "display_name" => 'Manage Products',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $viewProductsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewProducts",
            "display_name" => "View Products",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $roles = Role::all();
        $permissionsArray = [];
        foreach($roles as $role)
        {
            $permissionsArray[] = [
                "permission_id" => $viewProductsPermissionId,
                "role_id" => $role->id,
            ];
        }
        DB::table('permission_role')->insert($permissionsArray);
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
