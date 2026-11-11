<?php

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUnblockStockPermissionToSuperstockistAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('superstockist_admin', function (Blueprint $table) {
            $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();
            $unblock_stock_permission = Permission::where(['name' => 'unBlockMyStock'])->first();
            
            $superStockistAdminRole->givePermissionTo($unblock_stock_permission);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('superstockist_admin', function (Blueprint $table) {
            //
        });
    }
}
