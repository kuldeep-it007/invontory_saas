<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateDistributorsAddInvSeries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
          Schema::table('distributors', function(Blueprint $table)
        {
            $table->integer('invoice_series',false)->nullable()->default(0)->after('payment_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::table('distributors', function (Blueprint $table){
            $table->dropColumn(['invoice_series']);
        });
    }
}
