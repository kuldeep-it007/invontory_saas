<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMarkupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('markup', function (Blueprint $table) {
            $table->increments('id');
            $table->string('customer_type');
            $table->integer('customer_id')->nullable();
            $table->string('category_code')->nullable()->default(null);
            $table->string('collection_code')->nullable()->default(null);
            $table->string('colour_code')->nullable()->default(null);
            $table->string('state_code')->nullable()->default(null);
            $table->string('city_code')->nullable()->default(null);
            $table->string('post_code')->nullable()->default(null);
            $table->float('markup')->nullable()->default(0);
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
        Schema::dropIfExists('markup');
    }
}
