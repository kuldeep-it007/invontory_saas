<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCoDistributorPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageCoDistributorGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','co_distributors')->first()->id;
        $addCoDistributorDiscountPermission = DB::table('permissions')->insertGetId([
            "name" => "addCoDistributorDiscounts",
            "display_name" => "add Co Distributor Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCoDistributorGroupID,
        ]);

        $deleteCoDistributorDiscountPermission = DB::table('permissions')->insertGetId([
            "name" => "deleteCoDistributorDiscounts",
            "display_name" => "Delete Co Distributor Discounts",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageCoDistributorGroupID,
        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        $manageCoDistributorPermission = DB::table('permissions')->select(['id'])->where('name','=','manageCoDistributors')->first()->id;

        DB::table('permission_role')->insert([
            [
                "permission_id" => $addCoDistributorDiscountPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $deleteCoDistributorDiscountPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $addCoDistributorDiscountPermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $deleteCoDistributorDiscountPermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $manageCoDistributorPermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $manageCoDistributorPermission,
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
