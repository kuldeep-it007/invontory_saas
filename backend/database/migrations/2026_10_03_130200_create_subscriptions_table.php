<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: `subscriptions` table.
 *
 * Associates a tenant with a plan and a lifecycle status (R4.6). `tenant_id`
 * here is a LOGICAL reference to the central `tenants` row, NOT a tenant-owned
 * discriminator column — subscriptions live in the central database alongside
 * the tenant registry, so there is a real FK to `tenants`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // FK -> tenants.id (central). Logical association of a tenant to its
            // subscription. Indexed via the composite index below.
            $table->string('tenant_id');

            // FK -> plans.id (central). constrained() adds FK + index (R38.2).
            $table->foreignId('plan_id')->constrained('plans');

            // Lifecycle status gate used by the subscription-status guard (R4.6, R5.7).
            $table->enum('status', ['active', 'past_due', 'cancelled', 'trial'])->default('active');

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->cascadeOnDelete();

            // Composite index (tenant_id, status): resolve a tenant's active
            // subscription and gate creation when past_due/cancelled (R5.7).
            $table->index(['tenant_id', 'status'], 'subscriptions_tenant_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
