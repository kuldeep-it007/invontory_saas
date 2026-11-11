<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionToAddDistributorStock extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageStockGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'manage_stock',
            "display_name" => 'Manage Stock',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $addStockPermissionId = DB::table('permissions')->insertGetId([
            "name" => "addStock",
            "display_name" => "Add Stock",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageStockGroupID,
        ]);


        DB::table('permission_role')->insert([
            [
                "permission_id" => $addStockPermissionId,
                "role_id"       => $distributorAdminRoleId
            ],
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
