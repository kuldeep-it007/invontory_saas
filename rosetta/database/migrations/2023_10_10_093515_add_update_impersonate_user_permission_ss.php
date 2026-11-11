<?php

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUpdateImpersonateUserPermissionSs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();

        $update_user_permission = Permission::where('name','=','updateUser')->first();
        $impersonate_permission = Permission::where('name','=','impersonate')->first();
        $superStockistAdminRole->givePermissionTo($update_user_permission);
        $superStockistAdminRole->givePermissionTo($impersonate_permission);
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
