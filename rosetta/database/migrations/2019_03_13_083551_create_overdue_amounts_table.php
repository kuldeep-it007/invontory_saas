<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOverdueAmountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('overdue_amounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('overdueable_id', false)->nullable();
            $table->string('overdueable_type')->nullable();
            $table->integer('company_id',false)->nullable()->default(null);
            $table->integer('distributor_id',false)->nullable()->default(null);
            $table->integer('order_id',false);
            $table->double('amount', 12, 2);
            $table->integer('overdue_date',false)->nullable()->default(null);
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
        Schema::dropIfExists('overdue_amounts');
    }
}
