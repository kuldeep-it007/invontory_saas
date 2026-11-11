<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateCoDistributorDiscountsPermissions extends Migration
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


        $manageCoDistributorDiscounts = DB::table('permissions')->select(['id'])->where('name','=','manageCoDistributorDiscounts')->first()->id;
        DB::table('permission_role')->insert([
            [
                "permission_id" => $manageCoDistributorDiscounts,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $manageCoDistributorDiscounts,
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
