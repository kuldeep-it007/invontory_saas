<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterActiveProductAttributesTableAddCompany extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('active_product_attributes', function (Blueprint $table) {
            $table->integer('company_id',false)->nullable()->default(null)->after('activable_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('active_product_attributes', function (Blueprint $table){
            $table->dropColumn(['company_id']);
        });
    }
}
