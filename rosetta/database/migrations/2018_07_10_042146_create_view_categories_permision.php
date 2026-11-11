<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViewCategoriesPermision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'categories',
            "display_name" => 'Manage Categories',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $viewCategoriesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewCategories",
            "display_name" => "View Categories",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        $createCategoriesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "createCategories",
            "display_name" => "Create Categories",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $companySalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id;
   
         DB::table('permission_role')->insert([
                [
                    "permission_id" => $viewCategoriesPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $viewCategoriesPermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $viewCategoriesPermissionId,
                    "role_id"       => $companySalesRepRoleId
                ],
                [
                    "permission_id" => $createCategoriesPermissionId,
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
