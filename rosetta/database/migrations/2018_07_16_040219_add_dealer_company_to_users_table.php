<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDealerCompanyToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('dealer_id',false)->after('distributor_id')->nullable()->default(null);
            $table->integer('company_id',false)->after('dealer_id')->nullable()->default(null);
            $table->boolean('active')->default(true)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dealer_id');
            $table->dropColumn('company_id');
            $table->dropColumn('active');
        });
    }
}
