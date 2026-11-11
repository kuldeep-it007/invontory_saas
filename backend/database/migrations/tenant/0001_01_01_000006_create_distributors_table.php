<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `distributors` table.
 *
 * A Distributor buys stock from the single tenant Company and sells on to
 * Dealers (R10.1, R10.3). Each Distributor belongs to exactly one Company;
 * because the tenant database holds exactly one `companies` row (R1.5), the
 * `company_id` FK always points at that singleton. Isolation is physical, so
 * there is no `tenant_id` column.
 *
 * Payment_Terms (credit limit, overdue balance limit, overdue hold days,
 * payment type) are stored as JSON on the Buyer record (R10.1, R26.1); updates
 * are persisted here and audited at the model/service layer (R10.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();

            // FK to the tenant's single Company (R10.1, R10.3). Indexed so the
            // "distributors of the company" listing and the FK join are fast
            // (R38.3, R38.4).
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // Indian GSTIN captured on the distributor record (R10.1).
            $table->string('gstin')->nullable();
            $table->text('address')->nullable();

            // Payment_Terms JSON (R10.1, R26.1): credit_limit, overdue_balance_limit,
            // overdue_hold_days, payment_type. Stored as a flexible JSON blob so
            // the terms schema can evolve without a migration.
            $table->json('payment_terms')->nullable();

            $table->timestamps();

            // Case-insensitive-ish ordered listing of distributors by name.
            $table->index('name', 'distributors_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};
