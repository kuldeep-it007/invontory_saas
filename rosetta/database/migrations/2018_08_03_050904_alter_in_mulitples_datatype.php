<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterInMulitplesDatatype extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `category_codes` MODIFY `in_multiples` DOUBLE(8,4)');
        DB::statement('ALTER TABLE `collection_codes` MODIFY `in_multiples` DOUBLE(8,4)');
        DB::statement('ALTER TABLE `colours` MODIFY `in_multiples` DOUBLE(8,4)');
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
