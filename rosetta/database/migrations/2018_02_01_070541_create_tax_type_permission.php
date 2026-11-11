<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxTypePermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissionGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'taxTypes',
            "display_name" => 'Manage Tax Types',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            "name" => "tax_type_lookup",
            "display_name" => "Lookup Tax Types",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,

        ]);

        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;

        DB::table('permission_role')->insert([
                [
                    "permission_id" => $permissionId,
                    "role_id"       => $superAdminRoleId
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
