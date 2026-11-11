<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMasterData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('master_data')->insert([
            "type" => "shipping_reserve",
            "key"  => "Optional",
            "value" => "Optional",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_reserve",
            "key"  => "Never",
            "value" => "Never",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_reserve",
            "key"  => "Always",
            "value" => "Always",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_method",
            "key"  => "AS AGREED",
            "value" => "As Agreed",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_method",
            "key"  => "EX WORKS",
            "value" => "Ex Works",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_method",
            "key"  => "SEA",
            "value" => "By Sea",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_advice",
            "key"  => "Partial",
            "value" => "Partial",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "shipping_advice",
            "key"  => "Complete",
            "value" => "Complete",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_application_method",
            "key"  => "Manual",
            "value" => "Manual",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_method",
            "key"  => "AS AGREED",
            "value" => "As Agreed",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_method",
            "key"  => "BANK",
            "value" => "Bank",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "1 MONTH",
            "value" => "1 MONTH",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "1 YEAR",
            "value" => "1 YEAR",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "1.5 MONTH",
            "value" => "1.5 MONTH",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "100% ADV",
            "value" => "100% ADV",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "100% ADVC",
            "value" => "100% ADVC",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "14 DAYS",
            "value" => "14 DAYS",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "2 MONTHS",
            "value" => "2 MONTHS",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "21 DAYS",
            "value" => "21 DAYS",
            "sort"  => "8",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "30/70",
            "value" => "30/70",
            "sort"  => "9",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "50% ADV",
            "value" => "50% ADV",
            "sort"  => "10",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "7 DAYS",
            "value" => "7 DAYS",
            "sort"  => "11",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "90 DAYS",
            "value" => "90 DAYS",
            "sort"  => "12",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "AS AGR",
            "value" => "AS AGR",
            "sort"  => "13",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "CM",
            "value" => "CM",
            "sort"  => "14",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "payment_terms",
            "key"  => "COD",
            "value" => "COD",
            "sort"  => "15",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "1 MONTH",
            "value" => "1 MONTH",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "1 YEAR",
            "value" => "1 YEAR",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "1.5 MONTH",
            "value" => "1.5 MONTH",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "100% ADV",
            "value" => "100% ADV",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "100% ADVC",
            "value" => "100% ADVC",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "14 DAYS",
            "value" => "14 DAYS",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "2 MONTHS",
            "value" => "2 MONTHS",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "21 DAYS",
            "value" => "21 DAYS",
            "sort"  => "8",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "30/70",
            "value" => "30/70",
            "sort"  => "9",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "50% ADV",
            "value" => "50% ADV",
            "sort"  => "10",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "7 DAYS",
            "value" => "7 DAYS",
            "sort"  => "11",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "90 DAYS",
            "value" => "90 DAYS",
            "sort"  => "12",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "AS AGR",
            "value" => "AS AGR",
            "sort"  => "13",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "CM",
            "value" => "CM",
            "sort"  => "14",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "cash_flow_payment_terms",
            "key"  => "COD",
            "value" => "COD",
            "sort"  => "15",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_bus_postin_grp",
            "key"  => "ADVANCE C",
            "value" => "ADVANCE C",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_bus_postin_grp",
            "key"  => "FOREIGN",
            "value" => "FOREIGN",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_bus_postin_grp",
            "key"  => "INTERCOMP",
            "value" => "INTERCOMP",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_bus_postin_grp",
            "key"  => "NATIONAL",
            "value" => "NATIONAL",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_bus_postin_grp",
            "key"  => "PUR EXP",
            "value" => "PUR EXP",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sell_to_addr",
            "key"  => "Company",
            "value" => "Company",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sell_to_addr",
            "key"  => "Person",
            "value" => "Person",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "territory_code",
            "key"  => "CENTRAL",
            "value" => "Central",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "territory_code",
            "key"  => "EAST",
            "value" => "East",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "territory_code",
            "key"  => "EXPORT",
            "value" => "Export",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "territory_code",
            "key"  => "NORTH",
            "value" => "North",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "territory_code",
            "key"  => "SOUTH",
            "value" => "South",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "territory_code",
            "key"  => "WEST",
            "value" => "West",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "billing_loc_code",
            "key"  => "Warehouse",
            "value" => "Warehouse",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
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
