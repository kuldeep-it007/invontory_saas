<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDealersTempTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_dealers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id', false);
            $table->integer('distributor_id', false);
            $table->text('text')->nullable()->default(null);
            $table->integer('created_by',false)->nullable();
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
        Schema::dropIfExists('temp_dealers');
    }
}
