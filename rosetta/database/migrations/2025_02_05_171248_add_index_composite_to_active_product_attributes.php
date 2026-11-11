<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexCompositeToActiveProductAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('active_product_attributes', function (Blueprint $table) {
            $table->index(['category_code', 'collection_code', 'deleted_at'], 'idx_category_code_collection_code_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('active_product_attributes', function (Blueprint $table) {
            $table->dropIndex('idx_category_code_collection_code_deleted_at');
        });
    }
}
