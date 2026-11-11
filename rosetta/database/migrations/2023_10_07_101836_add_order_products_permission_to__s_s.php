<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddOrderProductsPermissionToSS extends Migration
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

        $order_products_permission = Permission::where('name','=','orderProducts')->first();
        $view_purchase_orders_permission = Permission::where('name','=','viewPurchaseOrders')->first();
        $manage_blocked_items_permission = Permission::where('name','=','manageBlockedItems')->first();
        $view_advance_orders_permission = Permission::where('name','=','viewAdvanceOrders')->first();
        $superStockistAdminRole->givePermissionTo($order_products_permission, $view_purchase_orders_permission, $manage_blocked_items_permission, $view_advance_orders_permission);
        $superStockistSalesRepRole->givePermissionTo($order_products_permission, $view_purchase_orders_permission, $manage_blocked_items_permission, $view_advance_orders_permission);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      
    }
}
