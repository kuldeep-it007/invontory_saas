<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCoDistributorPricingPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageCoDistributorGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'co_distributors',
            "display_name" => 'Co Distributors',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;

        $distributorSalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorSalesRep')->first()->id;

        $manageCoDistributorDiscountPermission = DB::table('permissions')->insertGetId([
            "name" => "manageCoDistributorDiscounts",
            "display_name" => "Manage Co Distributor Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCoDistributorGroupID,
        ]);

        $manageCoDistributorsPermission = DB::table('permissions')->insertGetId([
            "name" => "manageCoDistributors",
            "display_name" => "Manage Co Distributors",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCoDistributorGroupID,
        ]);

        $viewCoDistributorOrdersPermission = DB::table('permissions')->insertGetId([
            "name" => "manageCoDistributorOrders",
            "display_name" => "Manage Co Distributor Orders",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCoDistributorGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $manageCoDistributorDiscountPermission,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $manageCoDistributorsPermission,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $viewCoDistributorOrdersPermission,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $viewCoDistributorOrdersPermission,
                "role_id"       => $distributorSalesRepRoleId
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
