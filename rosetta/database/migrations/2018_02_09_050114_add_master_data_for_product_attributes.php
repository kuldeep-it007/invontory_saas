<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMasterDataForProductAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "BOX",
            "value" => "BOX",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "KG",
            "value" => "KG",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "MTR",
            "value" => "MTR",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "NOS",
            "value" => "NOS",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "PALLET",
            "value" => "PALLET",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "PCS",
            "value" => "PCS",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "ROLL",
            "value" => "ROLL",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "SFT",
            "value" => "SFT",
            "sort"  => "8",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "base_unit_of_measure",
            "key"  => "SQM",
            "value" => "SQM",
            "sort"  => "9",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "BOX",
            "value" => "BOX",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "KG",
            "value" => "KG",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "MTR",
            "value" => "MTR",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "NOS",
            "value" => "NOS",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "PALLET",
            "value" => "PALLET",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "PCS",
            "value" => "PCS",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "ROLL",
            "value" => "ROLL",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "SFT",
            "value" => "SFT",
            "sort"  => "8",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "purchase_unit_of_measure",
            "key"  => "SQM",
            "value" => "SQM",
            "sort"  => "9",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "BOX",
            "value" => "BOX",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "KG",
            "value" => "KG",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "MTR",
            "value" => "MTR",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "NOS",
            "value" => "NOS",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "PALLET",
            "value" => "PALLET",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "PCS",
            "value" => "PCS",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "ROLL",
            "value" => "ROLL",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "SFT",
            "value" => "SFT",
            "sort"  => "8",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "sales_unit_of_measure",
            "key"  => "SQM",
            "value" => "SQM",
            "sort"  => "9",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "item_tracking_code",
            "key"  => "FREEENTRY",
            "value" => "FREE ENTRY",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "item_tracking_code",
            "key"  => "LOTALL",
            "value" => "LOT ALL",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "item_tracking_code",
            "key"  => "LOTALLEXP",
            "value" => "LOT ALL EXP",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "item_tracking_code",
            "key"  => "LOTSNSALES",
            "value" => "LOT SN SALES",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "item_tracking_code",
            "key"  => "SNALL",
            "value" => "SN ALL",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "item_tracking_code",
            "key"  => "SNSALES",
            "value" => "SN SALES",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "vat_prod_posting_grp",
            "key"  => "NO VAT",
            "value" => "NO VAT",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "vat_prod_posting_grp",
            "key"  => "VAT 12.5",
            "value" => "VAT12.5",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "vat_prod_posting_grp",
            "key"  => "VAT 4",
            "value" => "VAT4",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "CHA CHARGE",
            "value" => "CHA CHARGE",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "FLOORING",
            "value" => "FLOORING",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "MANUFACT",
            "value" => "MANUFACT",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "MISC",
            "value" => "MISC",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "NO VAT",
            "value" => "NO VAT",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "RAW MAT",
            "value" => "RAW MAT",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "gen_prod_posting_grp",
            "key"  => "SERVICES",
            "value" => "SERVICES",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "ACCESSORY",
            "value" => "ACCESSRY",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "ARTTURF",
            "value" => "ARTTURF",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "CARPET",
            "value" => "CARPET",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "CARPETS",
            "value" => "CARPETS",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "CARPETTILE",
            "value" => "CARPETTILE",
            "sort"  => "5",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "CATALOGUES",
            "value" => "CATALOGUES",
            "sort"  => "6",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "DISPLAY",
            "value" => "DISPLAY",
            "sort"  => "7",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "DURRIE",
            "value" => "DURRIE",
            "sort"  => "8",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "LAMINATE",
            "value" => "LAMINATE",
            "sort"  => "9",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "PARQUET",
            "value" => "PARQUET",
            "sort"  => "10",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "PVC FLOOR",
            "value" => "PVC FLOOR",
            "sort"  => "11",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "RAWMAT",
            "value" => "RAWMAT",
            "sort"  => "12",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "RUBBERFLR",
            "value" => "RUBBERFLR",
            "sort"  => "13",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "SWISSFLOOR",
            "value" => "SWISSFLOOR",
            "sort"  => "14",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "WALLPAPER",
            "value" => "WALLPAPER",
            "sort"  => "15",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "inv_posting_grp",
            "key"  => "WPC DECK",
            "value" => "WPC DECK",
            "sort"  => "16",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "reorder_policy",
            "key"  => "Fixed Reorder Quantity",
            "value" => "Fixed_Reorder_Qty",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "reorder_policy",
            "key"  => "Maximum Quantity",
            "value" => "Maximun_Qty",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "reorder_policy",
            "key"  => "Order",
            "value" => "Order",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "reorder_policy",
            "key"  => "Lot-for-Lot",
            "value" => "Lot_for_Lot",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "tax_grp_code",
            "key"  => "EXEMPT",
            "value" => "Exempted Goods",
            "sort"  => "1",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "tax_grp_code",
            "key"  => "TAX_A",
            "value" => "12.5% Item tax 2% against C Form",
            "sort"  => "2",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "tax_grp_code",
            "key"  => "TAX_B",
            "value" => "5.5% Item tax 2% against C Form",
            "sort"  => "3",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "tax_grp_code",
            "key"  => "TAX_C",
            "value" => "12.5% Retention F.A",
            "sort"  => "4",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('master_data')->insert([
            "type" => "tax_grp_code",
            "key"  => "TAX_D",
            "value" => "5.5% Retention F.A",
            "sort"  => "5",
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
