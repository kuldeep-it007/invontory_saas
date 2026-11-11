<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: `plan_limits` table.
 *
 * Flexible key/value usage quota attached to a plan (R4.2). Supported keys
 * include max_distributors, max_dealers, max_dealers_per_distributor,
 * max_products, max_stock_points, max_users, max_orders_per_month,
 * max_storage_mb (R4.3). A value of 0 or NULL means unlimited (R4.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();

            // FK -> plans.id. foreignId()->constrained() adds the FK + index so
            // all limits for a plan load without a table scan (R38.2).
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();

            // Quota key (e.g. max_products). Stored as a string for flexibility (R4.2, R4.3).
            $table->string('key');

            // Quota value; nullable because 0 OR NULL both mean "unlimited" (R4.4).
            $table->unsignedBigInteger('value')->nullable();

            $table->timestamps();

            // Composite index (plan_id, key): QuotaService resolves a single
            // quota for a plan by (plan_id, key); also guarantees one row per
            // key per plan via the UNIQUE constraint (R5.1).
            $table->unique(['plan_id', 'key'], 'plan_limits_plan_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
