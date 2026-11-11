<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Rosetta\Models\User;

class AddDepotMgrRolesAndPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $depotManager_role = Role::create(['name' => 'depotManager']);
        $view_depot_orders_permission = Permission::create(['name' => 'viewDepotOrders']);
        $view_stock_point_permission = Permission::where('name','=','viewStockPoints')->first();
        $depotManager_role->givePermissionTo([$view_depot_orders_permission, $view_stock_point_permission]);

        $depotManager = new User;
        $depotManager->name = 'Depot Manager';
        $depotManager->email = 'depotManager@rosettaproducts.com';
        $depotManager->password = bcrypt('passw0rd!');
        $depotManager->assignRole('depotManager');
        $depotManager->save();
    }//#776

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
