<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Rosetta\Models\User;

class CreateApiUserRolePermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $apiUser_role = Role::create(['name' => 'apiUser']);
        $view_inter_depot_orders_permission = Permission::create(['name' => 'viewInterDepotOrders']);
        $view_depot_orders_permission = Permission::where('name','=','viewDepotOrders')->first();
        $apiUser_role->givePermissionTo([$view_inter_depot_orders_permission, $view_depot_orders_permission]);

        $apiUser = new User;
        $apiUser->name = 'API User';
        $apiUser->email = 'apiUser@rosettaproducts.com';
        $apiUser->password = bcrypt('passw0rd!');
        $apiUser->assignRole('apiUser');
        $apiUser->save();
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
