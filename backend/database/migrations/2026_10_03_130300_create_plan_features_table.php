<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: `plan_features` table.
 *
 * A Feature_Flag is a PER-PLAN BOOLEAN that gates optional modules (glossary,
 * R4.5) — distinct from a {@see plan_limits} quota, which is a key/value
 * integer. Feature flags are therefore modelled as their own key/boolean rows
 * rather than being overloaded onto `plan_limits` (where 0/NULL already carries
 * the "unlimited" meaning from R4.4, which would clash with "flag disabled").
 *
 * Supported flag keys (R4.5): `whatsapp_notifications`, `otp_login`,
 * `advance_orders`, `exports`, `api_access`, `ai_reports`. The subscription +
 * quota guard reads these per-plan flags from the central database to gate
 * optional modules (R5.4), and the AI layer additionally requires the
 * `AI_REPORTS_ENABLED` env master switch (R30.6/R30.7). Lives in the central
 * database because plans are cross-tenant platform configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();

            // FK -> plans.id. foreignId()->constrained() adds the FK + index so
            // all feature flags for a plan load without a table scan (R38.2).
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();

            // Feature-flag key (e.g. whatsapp_notifications). Stored as a string
            // for a flexible, enumerated allow-list (R4.5).
            $table->string('key');

            // Per-plan boolean gate (R4.5). Defaults to disabled: optional
            // modules are off unless a plan explicitly enables them.
            $table->boolean('enabled')->default(false);

            $table->timestamps();

            // Composite UNIQUE (plan_id, key): the feature-flag guard resolves a
            // single flag for a plan by (plan_id, key); the UNIQUE constraint
            // also guarantees one row per flag per plan (R5.4).
            $table->unique(['plan_id', 'key'], 'plan_features_plan_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
