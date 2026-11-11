<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddManageBlockedItemsPermissionToDepotManager extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $depotManager_role = Role::where('name','=','depotManager')->first();
        $manage_blocked_items_permission = Permission::where('name','=','manageBlockedItems')->first();
        $depotManager_role->givePermissionTo($manage_blocked_items_permission);

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
