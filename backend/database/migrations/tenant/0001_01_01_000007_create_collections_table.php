<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `collections` table.
 *
 * Middle level of the catalog hierarchy Category → Collection → Colour
 * (R11.2). Each Collection belongs to exactly one Category. Tenant-owned, no
 * `tenant_id` column (physical isolation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();

            // FK -> categories.id. constrained() adds the FK + index so every
            // collection under a category loads without a table scan, and the
            // catalog hierarchy join is covered (R11.2, R38.2, R38.4).
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->string('name');
            $table->timestamps();

            // name index: catalog browse/management filters collections by name
            // within the tenant database (R11.2, R38.4).
            $table->index('name', 'collections_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
