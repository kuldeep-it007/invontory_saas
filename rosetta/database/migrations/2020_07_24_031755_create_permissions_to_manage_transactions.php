<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePermissionsToManageTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageTransactionsGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'manage_transactions',
            "display_name" => 'Manage Transactions',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        $manageTransactionsPermission = DB::table('permissions')->insertGetId([
            "name" => "manageTransactions",
            "display_name" => "Manage Transaction",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageTransactionsGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $manageTransactionsPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $manageTransactionsPermission,
                "role_id"       => $companyAdminRoleId
            ]
        ]);
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
