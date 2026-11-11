<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: `plans` table.
 *
 * A subscription tier defining price, billing cycle, and active state (R4.1).
 * Quota values are stored separately in `plan_limits`. Lives in the central
 * database because plans are cross-tenant platform configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Price >= 0.0 (R4.1). DECIMAL for exact monetary representation.
            $table->decimal('price', 12, 2)->default(0);

            // Billing cycle restricted to the two supported values (R4.1).
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');

            // Active indicator so inactive plans can be hidden from selection (R4.1).
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Filter index: plan listings commonly filter by active state when
            // presenting selectable tiers during provisioning / plan changes (R4.1).
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
