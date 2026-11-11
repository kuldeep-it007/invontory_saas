<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RemoveManageBlockedItemsPermissionsFromSs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superStockistSalesRepRole = Role::where('name','=','superStockistSalesRep')->first();
        $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();

       
        $manage_blocked_items_permission = Permission::where('name','=','manageBlockedItems')->first();

        $superStockistAdminRole->revokePermissionTo($manage_blocked_items_permission);
        $superStockistSalesRepRole->revokePermissionTo($manage_blocked_items_permission);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ss', function (Blueprint $table) {
            //
        });
    }
}
