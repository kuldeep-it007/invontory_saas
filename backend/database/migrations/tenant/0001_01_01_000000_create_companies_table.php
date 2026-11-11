<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `companies` table.
 *
 * The single top-level organization that owns master inventory. Each tenant
 * database contains EXACTLY ONE company row (R1.5). Because isolation is
 * physical (one database per tenant) there is no `tenant_id` column and no
 * `unique(tenant_id)` constraint; the singleton rule is enforced in the
 * application layer (a one-company check) rather than by a DB constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gstin')->nullable();
            $table->timestamps();

            // No tenant_id, no composite tenant index: isolation is physical and
            // there is only ever one row, so no filter index is required here (R1.5).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
