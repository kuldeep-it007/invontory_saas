<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCollectionCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('collection_codes', function (Blueprint $table) {
            $table->dropColumn(['id']);
            $table->dropColumn(['warehouse_class_code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('collection_codes', function (Blueprint $table){
            $table->string('id');
            $table->string('warehouse_class_code')->after('description');
        });
    }
}
