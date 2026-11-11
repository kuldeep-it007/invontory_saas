<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCategoryCodesTableAddCl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('category_codes', function (Blueprint $table){
            $table->boolean('allow_cl')->default(false)->after('description');
            $table->float('cl_percent')->nullable()->default(null)->after('allow_cl');
            $table->float('in_multiples')->nullable()->default(null)->after('cl_percent');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('category_codes', function (Blueprint $table){
            $table->dropColumn(['cl_percent']);
            $table->dropColumn(['allow_cl']);
            $table->dropColumn(['in_multiples']);
        });
    }
}
