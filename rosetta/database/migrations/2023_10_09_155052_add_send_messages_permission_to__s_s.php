<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddSendMessagesPermissionToSS extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superStockistSalesRepRole = Role::where('name','=','superStockistSalesRep')->first();
        $superStockistAdminRole = Role::where('name','=','superStockistAdmin')->first();

        $send_messages_permission = Permission::where('name','=','sendAdvanceMessages')->first();
        $superStockistAdminRole->givePermissionTo($send_messages_permission);
        $superStockistSalesRepRole->givePermissionTo($send_messages_permission);
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
