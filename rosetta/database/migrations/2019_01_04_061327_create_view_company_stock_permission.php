<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViewCompanyStockPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'companyStock',
            "display_name" => 'Manage Company Stock',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $companySalesRepId = DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id;
        
        $viewCompanyStockPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewCompanyStock",
            "display_name" => "View Company Stock",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewCompanyStockPermissionId,
                "role_id"       => $companySalesRepId
            ],
            [
                "permission_id" => $viewCompanyStockPermissionId,
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
