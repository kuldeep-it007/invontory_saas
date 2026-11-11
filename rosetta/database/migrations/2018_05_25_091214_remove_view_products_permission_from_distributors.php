<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveViewProductsPermissionFromDistributors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $dealerAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','dealerAdmin')->first()->id;
        $dealerSalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','dealerSalesRep')->first()->id;
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        $distributorSalesRepRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorSalesRep')->first()->id;

        $viewProductPermissionId = DB::table('permissions')->select(['id'])->where('name','=','viewProducts')->first()->id;

        DB::table('permission_role')->where('permission_id','=',$viewProductPermissionId)
            ->where('role_id','=',$dealerAdminRoleId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$viewProductPermissionId)
            ->where('role_id','=',$dealerSalesRepRoleId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$viewProductPermissionId)
            ->where('role_id','=',$distributorAdminRoleId)
            ->delete();

        DB::table('permission_role')->where('permission_id','=',$viewProductPermissionId)
            ->where('role_id','=',$distributorSalesRepRoleId)
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
