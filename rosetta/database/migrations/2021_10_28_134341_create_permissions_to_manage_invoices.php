<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreatePermissionsToManageInvoices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $manageInvoicesGroupID = DB::table('permission_groups')->insertGetId([
            "name" => 'manage_invoices',
            "display_name" => 'Manage Invoices',
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U')
        ]);
        $superAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','superadmin')->first()->id;
        $companyAdminRoleId = DB::table('roles')->select(['id'])->where('name','=','companyAdmin')->first()->id;

        $viewInvoicesPermission = DB::table('permissions')->insertGetId([
            "name" => "viewInvoices",
            "display_name" => "View Invoices",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageInvoicesGroupID,
        ]);

        $printInvoicesPermission = DB::table('permissions')->insertGetId([
            "name" => "printInvoices",
            "display_name" => "Print Invoices",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
            "permission_group_id" => $manageInvoicesGroupID,
        ]);

        DB::table('permission_role')->insert([
            [
                "permission_id" => $viewInvoicesPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $viewInvoicesPermission,
                "role_id"       => $companyAdminRoleId
            ],
            [
                "permission_id" => $printInvoicesPermission,
                "role_id"       => $superAdminRoleId
            ],
            [
                "permission_id" => $printInvoicesPermission,
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
