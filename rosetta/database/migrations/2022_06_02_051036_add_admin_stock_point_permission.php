<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddAdminStockPointPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superadmin_role = Role::where('name','=','superadmin')->first();
        $companyAdmin_role = Role::where('name','=','companyAdmin')->first();
        $companySalesRep_role = Role::where('name','=','companySalesRep')->first();

        $view_stock_point_admin_permission = Permission::create(['name' => 'viewStockPointsAdmin']);
    

        $superadmin_role->givePermissionTo($view_stock_point_admin_permission);
        $companyAdmin_role->givePermissionTo($view_stock_point_admin_permission);
        $companySalesRep_role->givePermissionTo($view_stock_point_admin_permission);
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
