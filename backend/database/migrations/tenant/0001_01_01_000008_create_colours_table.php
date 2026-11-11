<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `colours` table.
 *
 * Leaf level of the catalog hierarchy Category → Collection → Colour (R11.2).
 * Each Colour belongs to exactly one Collection. Tenant-owned, no `tenant_id`
 * column (physical isolation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colours', function (Blueprint $table) {
            $table->id();

            // FK -> collections.id. constrained() adds the FK + index so every
            // colour under a collection loads without a table scan and the
            // hierarchy join is covered (R11.2, R38.2, R38.4).
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();

            $table->string('name');
            $table->timestamps();

            // name index: catalog browse/management filters colours by name
            // within the tenant database (R11.2, R38.4).
            $table->index('name', 'colours_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colours');
    }
};
