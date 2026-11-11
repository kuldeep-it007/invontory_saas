<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoDistributorPricing extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('co_distributor_discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('distributor_id',false);
            $table->integer('customer_id')->nullable();
            $table->string('category_code')->nullable()->default(null);
            $table->string('collection_code')->nullable()->default(null);
            $table->string('colour_code')->nullable()->default(null);
            $table->float('discount')->nullable()->default(0);
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
         Schema::dropIfExists('co_distributor_discounts');
    }
}
