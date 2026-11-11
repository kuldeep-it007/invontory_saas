<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViewCollectionsPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'collections',
            "display_name" => 'Manage Collections',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $viewCollectionsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewCollections",
            "display_name" => "View Collections",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $createCollectionsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createCollections",
            "display_name" => "Create Collections",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $companySalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id;
   
         DB::table('permission_role')->insert([
                [
                    "permission_id" => $viewCollectionsPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewCollectionsPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $viewCollectionsPermissionId,
                    "role_id"       => $companySalesRepRoleId
                ],
                [
                    "permission_id" => $createCollectionsPermissionId,
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
