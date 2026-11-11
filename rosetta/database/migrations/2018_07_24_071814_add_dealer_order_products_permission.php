<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDealerOrderProductsPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $producstGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','products')->first()->id;

        $dealerSalesRepId = DB::table('roles')->select(['id'])->where('name','=','dealerSalesRep')->first()->id;
        $dealerAdminId = DB::table('roles')->select(['id'])->where('name','=','dealerAdmin')->first()->id;

        $orderProductsPermissionId = DB::table('permissions')->select(['id'])->where('name','=','orderProducts')->first()->id;

        DB::table('permission_role')->insert([
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $dealerSalesRepId
            ],
            [
                "permission_id" => $orderProductsPermissionId,
                "role_id"       => $dealerAdminId
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
