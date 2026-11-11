<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPermissionToOrderProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $producstGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','products')->first()->id;

        $companySalesRepId =DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id; 
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $distributorSalesRepId = DB::table('roles')->select(['id'])->where('name','=','distributorSalesRep')->first()->id;
        $distributorAdminId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;

        $orderProductsPermissionId = DB::table('permissions')->insertGetId([
            "name" => "orderProducts",
            "display_name" => "Order Products",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $producstGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $companySalesRepId
            ],
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $distributorSalesRepId
            ],
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $distributorAdminId
            ]
        ]);
        /*
        
        
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','dealers')->first()->id;

        $approvePermissionId = DB::table('permissions')->insertGetId([
            "name" => "approveDealers",
            "display_name" => "Approve Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $rejectPermissionId = DB::table('permissions')->insertGetId([
            "name" => "rejectDealers",
            "display_name" => "Reject Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        DB::table('permission_role')->insert([
                [
                    "permission_id" => $approvePermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $approvePermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $rejectPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $rejectPermissionId,
                    "role_id"       => $companyAdminRoleId
                ]
            ]);*/
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
