<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesOrderPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $salesOrdersGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'sales_orders',
            "display_name" => 'Manage Sales Orders',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $viewPurchaseOrdersPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewSalesOrders",
            "display_name" => "View Sales Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $salesOrdersGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewPurchaseOrdersPermissionId,
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
