<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWarrantyTermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('warranty_terms', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id',false)->nullable()->default(null);
            $table->string('category_code')->nullable()->default(null);
            $table->string('collection_code')->nullable()->default(null);
            $table->string('colour_code')->nullable()->default(null);
            $table->text('description');
            $table->string('duration');
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
        Schema::dropIfExists('warranty_terms');
    }
}
