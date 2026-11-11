<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `dealers` table.
 *
 * A Dealer buys from a Distributor and (by default) consumes stock without
 * holding any (R10.2, R10.3, R13.6). Each Dealer belongs to exactly one
 * Distributor. Isolation is physical, so there is no `tenant_id` column.
 *
 * Payment_Terms are stored as JSON on the Buyer record (R10.2, R26.1); updates
 * are persisted here and audited at the model/service layer (R10.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();

            // FK to the owning Distributor (R10.2, R10.3). Indexed so both the
            // "dealers of a distributor" listing and the per-distributor dealer
            // count (max_dealers_per_distributor enforcement, R10.4) are fast
            // (R38.3, R38.4).
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // Indian GSTIN captured on the dealer record (R10.2).
            $table->string('gstin')->nullable();
            $table->text('address')->nullable();

            // Payment_Terms JSON (R10.2, R26.1): see distributors table.
            $table->json('payment_terms')->nullable();

            $table->timestamps();

            // Ordered listing of dealers by name within a distributor.
            $table->index('name', 'dealers_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealers');
    }
};
