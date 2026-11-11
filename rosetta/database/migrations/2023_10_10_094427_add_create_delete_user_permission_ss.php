<?php

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreateDeleteUserPermissionSs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();

        $create_user_permission = Permission::where('name','=','createUser')->first();
        $delete_permission = Permission::where('name','=','deleteUser')->first();
        $superStockistAdminRole->givePermissionTo($create_user_permission);
        $superStockistAdminRole->givePermissionTo($delete_permission);
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
