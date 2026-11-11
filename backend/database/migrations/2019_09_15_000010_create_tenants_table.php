<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: `tenants` table (stancl/tenancy).
 *
 * Holds one row per tenant in the CENTRAL database. The `id` is the
 * stancl/tenancy tenant id (used to derive the per-tenant database name,
 * `tenant` + id). Tenant-owned business data lives in each tenant's own
 * database; this table is the authoritative cross-tenant registry only
 * (R1.1). Isolation is physical, so no tenant-owned table carries a
 * `tenant_id` discriminator.
 */
class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // Subdomain under which the tenant is resolved. A UNIQUE index both
            // enforces the "one subdomain per tenant" rule (R1.4, R1.5) and
            // serves as the fast resolution path when stancl/tenancy identifies
            // the tenant from the request host. Lives in the central DB.
            $table->string('subdomain')->unique();

            // Each tenant operates in a single currency (open-question default).
            // Stored as an ISO 4217 code; used when resolving monetary values.
            $table->string('currency', 3)->default('USD');

            // Per-tenant toggle: when enabled, order confirmations may drive
            // stock below zero and the backordered quantity is recorded (R17.2).
            $table->boolean('backorders_enabled')->default(false);

            // Per-tenant toggle: when enabled, dealers may own stock points and
            // hold stock; off by default so dealers are pure consumers (R13.6, R13.7).
            $table->boolean('dealer_stock_enabled')->default(false);

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
