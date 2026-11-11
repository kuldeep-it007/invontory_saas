<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoSuperStockistsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('co_super_stockists', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('super_stockist_id',false);
            $table->integer('co_super_stockist_id',false);
            $table->double('credit_limit',12,2)->nullable()->default(0.0);
            $table->double('overdue_balance',12,2)->nullable()->default(0.0);
            $table->integer('overdue_hold_days',false)->nullable()->default(null);
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
        Schema::dropIfExists('so_super_stockists');
    }
}
