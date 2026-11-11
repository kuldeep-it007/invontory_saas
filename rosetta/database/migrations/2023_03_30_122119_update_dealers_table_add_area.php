<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDealersTableAddArea extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dealers', function(Blueprint $table)
        {
            $table->string('area')->nullable()->default(null)->after('gstin');
            $table->boolean('updated_folder_given')->default(false)->after('area'); 
            $table->boolean('app_explained')->default(false)->after('updated_folder_given'); 
            $table->text('notes')->nullable()->default(null)->after('app_explained');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dealers', function (Blueprint $table){
            $table->dropColumn(['notes']);
            $table->dropColumn(['app_explained']);
            $table->dropColumn(['updated_folder_given']);
            $table->dropColumn(['area']);
        });
    }
}
