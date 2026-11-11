<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActiveProductAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('active_product_attributes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('activable_id', false);
            $table->string('activable_type');
            $table->string('category_code');
            $table->string('category_display')->nullable()->default(null);
            $table->string('collection_code')->nullable()->default(null);
            $table->string('collection_display')->nullable()->default(null);
            $table->string('colour_code')->nullable()->default(null);
            $table->string('colour_display')->nullable()->default(null);
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
            $table->integer('deleted_at',false)->nullable()->default(null);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('active_product_attributes');
    }
}
