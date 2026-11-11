<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddClRatesPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageCLRatesGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'cl_rates',
            "display_name" => 'Manage CL Rates',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        $distributorSalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorSalesRep')->first()->id;

        $viewCLRatePermission = DB::table('permissions')->insertGetId([
            "name" => "viewCLRates",
            "display_name" => "View CL Rates",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCLRatesGroupID,
        ]);

        $addCLRatePermisssion = DB::table('permissions')->insertGetId([
            "name" => "addCLRates",
            "display_name" => "Add CL Rates",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCLRatesGroupID,
        ]);

        $deleteCLRatePermisssion = DB::table('permissions')->insertGetId([
            "name" => "deleteCLRates",
            "display_name" => "Manage Co Distributor Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCLRatesGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewCLRatePermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewCLRatePermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $viewCLRatePermission,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $viewCLRatePermission,
                "role_id"       => $distributorSalesRepRoleId
            ],
            [
                "permission_id" => $addCLRatePermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addCLRatePermisssion,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $addCLRatePermisssion,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $deleteCLRatePermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteCLRatePermisssion,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteCLRatePermisssion,
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
