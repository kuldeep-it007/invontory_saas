<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rosetta\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class InsertDataIntoModelHasRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    use HasRoles;
    public function up()
    {
        $users = User::all();
        foreach($users as $user){
            if($user->dealer_id != null)
            {
                $user->assignRole('dealerAdmin');
            }
            elseif($user->distributor_id != null)
            {
                $user->assignRole('distributorAdmin');
            }
            elseif($user->company_id != null)
            {
                $user->assignRole('companyAdmin');
            }
            else
            {
                $user->assignRole('superadmin');
            }
            $user->save();
        }
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
