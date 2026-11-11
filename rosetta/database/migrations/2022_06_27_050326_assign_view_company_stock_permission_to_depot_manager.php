<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignViewCompanyStockPermissionToDepotManager extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $depotManager_role = Role::where('name','=','depotManager')->first();
        $view_company_stock_permission = Permission::where('name','=','viewCompanyStock')->first();
        $depotManager_role->givePermissionTo($view_company_stock_permission);
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
