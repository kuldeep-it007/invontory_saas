<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `ai_reports` table (M5b, R32).
 *
 * Persists the outcome of a natural-language AI report generation so the
 * follow-on `POST /ai/reports/{id}/summary` and `POST /ai/reports/{id}/followup`
 * endpoints can reference the ALREADY-COMPUTED dataset by id (design "AI
 * Reporting Layer" — `POST /ai/reports/{id}/summary`, `.../followup`).
 *
 * Summaries and follow-ups are computed solely from this stored dataset
 * (R32.1, R32.2): the row keeps the resolved {@see \App\Reports\ReportQuerySpec}
 * (JSON, so the exact scoped/defaulted query is reproducible) and the returned
 * `dataset` rows themselves, so no fresh query — and no PII beyond what the
 * report already returned — is ever needed to summarise or answer a follow-up.
 *
 * Isolation is physical: this is a tenant-owned table reached only once tenancy
 * is initialized, so there is NO `tenant_id` column (R28.10). Scoping to the
 * user's tenant+role is inherited from the active tenant database plus the
 * principal captured in `created_by` (R32.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reports', function (Blueprint $table) {
            $table->id();

            // The user's original natural-language request (R31.1). Retained so
            // the audit trail and any follow-up carry the asking context.
            $table->text('prompt');

            // The resolved report type (an allow-listed ReportType value).
            $table->string('report_type');

            // The resolved ReportQuerySpec (filters WITH the applied
            // previous-month default and role scoping, grouping, sort,
            // pagination) as JSON, so the exact query that produced the dataset
            // is reproducible and auditable (R31.3, R32.4).
            $table->json('spec');

            // The returned dataset rows (selected/aggregated columns only).
            // Summaries and follow-ups are computed SOLELY from this stored
            // dataset (R32.1, R32.2) — never from a fresh unbounded query.
            $table->json('dataset');

            // The last AI-generated natural-language summary of the dataset
            // (takeaways/trends/outliers), cached after the first request so a
            // repeat summary need not re-invoke the model (R32.1). Null until a
            // summary is requested, or when the AI layer degrades to a no-op.
            $table->text('summary')->nullable();

            // The principal that generated the report (audit + role scope —
            // R32.4, R32.5).
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // (created_by, id): list a principal's own AI reports newest-first.
            $table->index(['created_by', 'id'], 'ai_reports_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reports');
    }
};
