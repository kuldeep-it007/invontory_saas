<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignViewStockPointPermissionToDealerAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $dealerAdmin_role = Role::where('name','=','dealerAdmin')->first();
        $view_stock_point_permission = Permission::where('name','=','viewStockPoints')->first();
        $dealerAdmin_role->givePermissionTo($view_stock_point_permission);
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
