<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManageCustomerDiscountPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $customerDiscountGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'customer_discounts',
            "display_name" => 'Manage Customer Discounts',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $listCustomerDiscountsGroupIDPermissionId = DB::table('permissions')->insertGetId([
            "name" => "viewCustomerDiscounts",
            "display_name" => "View Customer Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $customerDiscountGroupID,
        ]);
        $manageCustomerDiscountsGroupIDPermissionId = DB::table('permissions')->insertGetId([
            "name" => "manageCustomerDiscounts",
            "display_name" => "Manage Customer Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $customerDiscountGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $listCustomerDiscountsGroupIDPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
            [
                "permission_id" => $manageCustomerDiscountsGroupIDPermissionId,
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
