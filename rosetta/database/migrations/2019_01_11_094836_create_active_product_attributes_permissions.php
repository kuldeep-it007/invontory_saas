<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActiveProductAttributesPermissions extends Migration
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

        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','products')->first()->id;

        $viewActiveProductAttributesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewActiveProductAttributes",
            "display_name" => "View Active Product Attributes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $addActiveProductAttributesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "addActiveProductAttributes",
            "display_name" => "Add Active Product Attributes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $updateActiveProductAttributesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "updateActiveProductAttributes",
            "display_name" => "Update Active Product Attributes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $deleteActiveProductAttributesPermissionId = DB::table('permissions')->insertGetId([
            "name" => "deleteActiveProductAttributes",
            "display_name" => "Delete Active Product Attributes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);
        
        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewActiveProductAttributesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addActiveProductAttributesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $updateActiveProductAttributesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteActiveProductAttributesPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewActiveProductAttributesPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $addActiveProductAttributesPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $updateActiveProductAttributesPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteActiveProductAttributesPermissionId,
                "role_id"       => $companyAdminRoleId
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
