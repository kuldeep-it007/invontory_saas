<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rosetta\Models\Role;
use Rosetta\Models\User;

class CreateSuperAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superAdminRole = new Role;
        $superAdminRole->name = 'superadmin';
        $superAdminRole->display_name = 'Super Admin';
        $superAdminRole->save();


        $superAdmin = new User;
        $superAdmin->name = 'Super Admin';
        $superAdmin->email = 'superadmin@rosettaproducts.com';
        $superAdmin->password = bcrypt('passw0rd!');
        $superAdmin->save();
        $superAdminRole = Role::where('name','=','superadmin')->first();
        $superAdmin->roles()->attach($superAdminRole->id);
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
