<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRegionCategoryTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('region_category_taxes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('region_id',false);
            $table->string('category_code')->nullable()->default(null);
            $table->string('tax_type_code');
            $table->double('tax_percent',8,2);
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
        Schema::dropIfExists('region_category_taxes');
    }
}
