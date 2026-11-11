<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cl_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('CLable_type');
            $table->integer('CLable_id',false);
            $table->string('category_code')->nullable()->default(null);
            $table->string('collection_code')->nullable()->default(null);
            $table->string('colour_code')->nullable()->default(null);
            $table->string('related_type')->nullable()->default(null);
            $table->integer('related_id',false)->nullable()->default(null);
            $table->double('percent',8,2);
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cl_rates');
    }
}
