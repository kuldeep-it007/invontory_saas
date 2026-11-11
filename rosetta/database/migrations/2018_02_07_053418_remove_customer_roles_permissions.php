<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveCustomerRolesPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('permissions')->where('name','=','customers')->delete();
        DB::table('permission_groups')->where('name','=','customers')->delete();
        DB::table('roles')->where('name','=','customerSalesRep')->delete();
        DB::table('roles')->where('name','=','customerAdmin')->delete();
        DB::table('roles')->where('name','=','salesRep')->delete();
        DB::table('roles')->where('name','=','admin')->delete();
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
