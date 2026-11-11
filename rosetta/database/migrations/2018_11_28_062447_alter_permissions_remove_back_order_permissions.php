<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterPermissionsRemoveBackOrderPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $viewBackOrdersPermissionId = DB::table('permissions')->select(['id'])->where('name','=','viewBackOrders')->first()->id;

        $createBackOrdersPermissionId = DB::table('permissions')->select(['id'])->where('name','=','createBackOrders')->first()->id;

        $updateBackOrdersPermissionId = DB::table('permissions')->select(['id'])->where('name','=','updateBackOrders')->first()->id;

        $deleteBackOrderLinesPermissionId = DB::table('permissions')->select(['id'])->where('name','=','deleteBackOrderLines')->first()->id;

        $deleteBackOrderLineItemsPermissionId = DB::table('permissions')->select(['id'])->where('name','=','deleteBackOrderLineItems')->first()->id;

        $deleteBackOrderPermissionId = DB::table('permissions')->select(['id'])->where('name','=','deleteBackOrder')->first()->id;

        DB::table('permissions')->where('id','=',$viewBackOrdersPermissionId)
            ->delete();

        DB::table('permissions')->where('id','=',$createBackOrdersPermissionId)
            ->delete();

        DB::table('permissions')->where('id','=',$updateBackOrdersPermissionId)
            ->delete();

        DB::table('permissions')->where('id','=',$deleteBackOrderLinesPermissionId)
            ->delete();

        DB::table('permissions')->where('id','=',$deleteBackOrderLineItemsPermissionId)
            ->delete();

        DB::table('permissions')->where('id','=',$deleteBackOrderPermissionId)
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
