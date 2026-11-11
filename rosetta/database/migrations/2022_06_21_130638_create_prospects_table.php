<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProspectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable()->default(null);
            $table->string('business_name')->nullable()->default(null);
            $table->string('type')->nullable()->default(null);
            $table->string('email')->unique()->nullable()->default(null);
            $table->string('phone')->unique()->nullable()->default(null);
            $table->string('password');
            $table->boolean('receive_updates')->default(false);
            $table->string('gst_no')->nullable()->default(null);
            $table->string('pan')->nullable()->default(null);
            $table->string('street')->nullable()->default(null);
            $table->string('floor_unit')->nullable()->default(null);
            $table->string('city')->nullable()->default(null);
            $table->string('state')->nullable()->default(null);
            $table->string('post_code')->nullable()->default(null);
            $table->string('country_code')->nullable()->default(null);
            $table->integer('created_by',false)->nullable();
            $table->integer('created_at',false)->nullable();
            $table->integer('updated_at',false)->nullable();
            $table->integer('deleted_at',false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prospects');
    }
}