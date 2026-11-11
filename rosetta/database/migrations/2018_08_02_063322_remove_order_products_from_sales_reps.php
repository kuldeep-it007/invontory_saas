<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoveOrderProductsFromSalesReps extends Migration
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

        $companySalesRepId =DB::table('roles')->select(['id'])->where('name','=','companySalesRep')->first()->id; 

        $distributorSalesRepId = DB::table('roles')->select(['id'])->where('name','=','distributorSalesRep')->first()->id;

        $dealerSalesRepId = DB::table('roles')->select(['id'])->where('name','=','dealerSalesRep')->first()->id;

        $orderProductsPermissionId = DB::table('permissions')->select(['id'])->where('name','=','orderProducts')->first()->id;

        DB::table('permission_role')->where('permission_id','=',$orderProductsPermissionId)
            ->where('role_id','=',$superAdminRoleId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$orderProductsPermissionId)
            ->where('role_id','=',$companyAdminRoleId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$orderProductsPermissionId)
            ->where('role_id','=',$companySalesRepId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$orderProductsPermissionId)
            ->where('role_id','=',$distributorSalesRepId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$orderProductsPermissionId)
            ->where('role_id','=',$dealerSalesRepId)
            ->delete();
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
