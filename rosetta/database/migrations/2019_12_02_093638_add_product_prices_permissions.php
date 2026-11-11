<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductPricesPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageProductPricesGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'product_prices',
            "display_name" => 'Manage Product Prices',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
       
        $viewProductPricePermission = DB::table('permissions')->insertGetId([
            "name" => "viewProductPrices",
            "display_name" => "View Product Prices",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageProductPricesGroupID,
        ]);

        $addProductPricePermisssion = DB::table('permissions')->insertGetId([
            "name" => "addProductPrices",
            "display_name" => "Add Product Prices",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageProductPricesGroupID,
        ]);

        $editProductPricePermisssion = DB::table('permissions')->insertGetId([
            "name" => "editProductPrices",
            "display_name" => "Edit Product Prices",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageProductPricesGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewProductPricePermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addProductPricePermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $editProductPricePermisssion,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewProductPricePermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $addProductPricePermisssion,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $editProductPricePermisssion,
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
