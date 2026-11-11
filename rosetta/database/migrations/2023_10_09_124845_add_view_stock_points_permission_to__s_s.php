<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddViewStockPointsPermissionToSS extends Migration
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

        $view_stock_points_permission = Permission::where('name','=','viewStockPoints')->first();
        $superStockistAdminRole->givePermissionTo($view_stock_points_permission);
        $superStockistSalesRepRole->givePermissionTo($view_stock_points_permission);
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
