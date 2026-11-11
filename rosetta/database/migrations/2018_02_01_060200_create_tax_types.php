<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('tax_types')->insert([
            "code" => "GST",
            "name" => "Goods & Service Tax",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "GST IMP",
            "name" => "GST on import purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "GST PUR",
            "name" => "GST Purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "GST SERVIC",
            "name" => "GST Service",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "IMPORTPURC",
            "name" => "Import Purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "P_TAX(EXL)",
            "name" => "CST excl. charges in Purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "P_TAX(INL)",
            "name" => "Tax incl. charges in Purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "S_TAX(EXL)",
            "name" => "Tax excl. charges in Purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "S_TAX(INL)",
            "name" => "Tax incl. charges in Sale",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "VAT",
            "name" => "Local Purchase",
            "created_at" => Carbon::now()->format('U'),
            "updated_at" => Carbon::now()->format('U'),
        ]);

        DB::table('tax_types')->insert([
            "code" => "VAT(EX)",
            "name" => "Vat Local",
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
