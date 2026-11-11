<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddManageSsDiscountsPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();
        $view_ss_discounts_users_permission = Permission::create(['name' => 'viewSSDiscounts']);
        $manage_ss_discounts_users_permission = Permission::create(['name' => 'manageSSDiscounts']);
        $superStockistAdminRole->givePermissionTo($view_ss_discounts_users_permission, $manage_ss_discounts_users_permission);
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
