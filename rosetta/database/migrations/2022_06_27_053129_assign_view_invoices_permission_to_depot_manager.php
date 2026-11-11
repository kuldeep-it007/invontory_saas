<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignViewInvoicesPermissionToDepotManager extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $depotManager_role = Role::where('name','=','depotManager')->first();
        $apiUser_role = Role::where('name','=','apiUser')->first();
        $view_depot_invoices_permission = Permission::create(['name' => 'viewDepotInvoices']);
        $print_depot_invoices_permission = Permission::create(['name' => 'printDepotInvoices']);
        $depotManager_role->givePermissionTo([$view_depot_invoices_permission,$print_depot_invoices_permission]);
        $apiUser_role->givePermissionTo([$view_depot_invoices_permission,$print_depot_invoices_permission]);
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
