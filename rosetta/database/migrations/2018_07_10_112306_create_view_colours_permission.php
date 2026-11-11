<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViewColoursPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'colours',
            "display_name" => 'Manage Colours',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $viewColoursPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewColours",
            "display_name" => "View Colours",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $createColoursPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createColours",
            "display_name" => "Create Colours",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $companySalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id;
   
         DB::table('permission_role')->insert([
                [
                    "permission_id" => $viewColoursPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewColoursPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $viewColoursPermissionId,
                    "role_id"       => $companySalesRepRoleId
                ],
                [
                    "permission_id" => $createColoursPermissionId,
                    "role_id"       => $superAdminRoleId
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
