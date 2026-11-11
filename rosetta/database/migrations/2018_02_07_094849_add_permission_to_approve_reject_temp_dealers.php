<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPermissionToApproveRejectTempDealers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;
        $permissionGroupID = DB::table('permission_groups')->select(['id'])->where('name','=','dealers')->first()->id;

        $approvePermissionId = DB::table('permissions')->insertGetId([
            "name" => "approveDealers",
            "display_name" => "Approve Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        $rejectPermissionId = DB::table('permissions')->insertGetId([
            "name" => "rejectDealers",
            "display_name" => "Reject Dealers",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $permissionGroupID,
        ]);

        DB::table('permission_role')->insert([
                [
                    "permission_id" => $approvePermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $approvePermissionId,
                    "role_id"       => $companyAdminRoleId
                ],
                [
                    "permission_id" => $rejectPermissionId,
                    "role_id"       => $superAdminRoleId
                ],
                [
                    "permission_id" => $rejectPermissionId,
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
