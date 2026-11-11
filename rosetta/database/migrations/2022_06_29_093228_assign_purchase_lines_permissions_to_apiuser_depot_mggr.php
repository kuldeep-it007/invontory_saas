<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignPurchaseLinesPermissionsToApiuserDepotMggr extends Migration
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
        $update_purchase_line_permission = Permission::where('name','=','updatePurchaseLines')->first();
        $delete_purchase_line_permission = Permission::where('name','=','deletePurchaseLines')->first();
        $depotManager_role->givePermissionTo([$update_purchase_line_permission,$delete_purchase_line_permission]);
        $apiUser_role->givePermissionTo([$update_purchase_line_permission,$delete_purchase_line_permission]);
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
