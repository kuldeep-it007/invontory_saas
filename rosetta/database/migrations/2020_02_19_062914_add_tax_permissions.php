<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaxPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageTaxesGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'taxes',
            "display_name" => 'Manage Taxes',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
      
        $viewTaxPermission = DB::table('permissions')->insertGetId([
            "name" => "viewTaxes",
            "display_name" => "View Taxes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageTaxesGroupID,
        ]);

        $addTaxPermisssion = DB::table('permissions')->insertGetId([
            "name" => "addTaxes",
            "display_name" => "Add Taxes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageTaxesGroupID,
        ]);

        $updateTaxPermisssion = DB::table('permissions')->insertGetId([
            "name" => "updateTaxes",
            "display_name" => "Update Taxes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageTaxesGroupID,
        ]);

        $deleteTaxPermisssion = DB::table('permissions')->insertGetId([
            "name" => "deleteTaxes",
            "display_name" => "Delete Taxes",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageTaxesGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewTaxPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewTaxPermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $addTaxPermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addTaxPermisssion,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $updateTaxPermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $updateTaxPermisssion,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteTaxPermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteTaxPermisssion,
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
