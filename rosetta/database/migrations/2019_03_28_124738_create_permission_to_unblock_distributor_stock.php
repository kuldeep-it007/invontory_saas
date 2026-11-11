<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionToUnblockDistributorStock extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $unBlockMyStockGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'unblock_my_stock',
            "display_name" => 'Unblock My Stock',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $distributorAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','distributorAdmin')->first()->id;
        
        $unBlockMyStockPermissionId = DB::table('permissions')->insertGetId([
            "name" => "unBlockMyStock",
            "display_name" => "Unblock My Stock",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $unBlockMyStockGroupID,
        ]);


        DB::table('permission_role')->insert([
            [
                "permission_id" => $unBlockMyStockPermissionId,
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
