<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesManagerAndRegionAndPriorityToDealersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->integer('sales_manager_id')
                  ->default(0);

            $table->string('region', 50)
                  ->nullable();

            $table->string('priority', 10)
                  ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn([
                'sales_manager_id',
                'region',
                'priority',
            ]);
        });
    }
}
