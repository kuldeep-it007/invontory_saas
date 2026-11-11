<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateUnblockCompanyStockPermissionAssignToSuperCompanyAdmin extends Migration
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
        $unblock_company_stock_permission = Permission::create(['name' => 'unBlockCompanyStock']);
        $superadmin_role->givePermissionTo($unblock_company_stock_permission);
        $companyAdmin_role->givePermissionTo($unblock_company_stock_permission);
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
