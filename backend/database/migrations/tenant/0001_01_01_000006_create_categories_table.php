<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `categories` table.
 *
 * Top level of the catalog hierarchy Category → Collection → Colour (R11.2).
 * A tenant-owned table living in the tenant's own database; because isolation
 * is physical there is no `tenant_id` column — a row written on the active
 * tenant connection belongs to that tenant automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();

            // name index: catalog management lists/filters categories by name
            // and active-attribute configuration resolves categories by name
            // within the tenant database (R11.2, R38.4).
            $table->index('name', 'categories_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
