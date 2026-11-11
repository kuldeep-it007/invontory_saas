<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddSuperStockistRolesPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superStockistSalesRepRole = Role::create(['name' => 'superStockistSalesRep']);
        $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();

        $view_super_stockist_permission = Permission::where('name','=','viewSuperStockists')->first();
        $add_super_stockist_users_permission = Permission::create(['name' => 'addSuperStockistUsers']);
        $update_super_stockist_users_permission = Permission::create(['name' => 'updateSuperStockistUsers']);
        $view_super_stockist_users_permission = Permission::create(['name' => 'viewSuperStockistUsers']);
        $delete_super_stockist_users_permission = Permission::create(['name' => 'deleteSuperStockistUsers']);
        $superStockistSalesRepRole->givePermissionTo($view_super_stockist_permission, $add_super_stockist_users_permission, $update_super_stockist_users_permission, $view_super_stockist_users_permission, $delete_super_stockist_users_permission);
        $superStockistAdminRole->givePermissionTo($add_super_stockist_users_permission, $update_super_stockist_users_permission, $view_super_stockist_users_permission, $delete_super_stockist_users_permission);
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
