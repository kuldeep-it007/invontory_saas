<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddSuperStockistRolePermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superAdminRole = Role::where('name','=','superadmin')->first();
        $superStockistAdminRole = Role::create(['name' => 'superStockistAdmin']);

        $view_super_stockist_permission = Permission::create(['name' => 'viewSuperStockists']);
        $create_super_stockist_permission = Permission::create(['name' => 'createSuperStockists']);
        $update_super_stockist_permission = Permission::create(['name' => 'updateSuperStockists']);
        $delete_super_stockist_permission = Permission::create(['name' => 'deleteSuperStockists']);

        $create_distributors_permission = Permission::where('name','=','createDistributors')->first();
        $update_distributors_permission = Permission::where('name','=','updateDistributors')->first();
        $delete_distributors_permission = Permission::where('name','=','deleteDistributors')->first();
        $view_distributors_permission = Permission::where('name','=','viewDistributors')->first();

        $superAdminRole->givePermissionTo([$view_super_stockist_permission, $create_super_stockist_permission, $update_super_stockist_permission, $delete_super_stockist_permission]);

        $superStockistAdminRole->givePermissionTo([$create_distributors_permission, $update_distributors_permission, $delete_distributors_permission, $view_distributors_permission]);

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
