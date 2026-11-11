<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `report_exports` table (R29.4, R29.5).
 *
 * Tracks a single report export request through its asynchronous lifecycle. A
 * large export runs as a queued streaming job (R29.5): the row is created in
 * the `pending` state, a worker flips it to `processing` while it streams rows
 * into the file, then `completed` with a populated download link — or `failed`
 * with an error message. The caller polls this row (by id) to learn when the
 * download link is ready.
 *
 * The row persists the EFFECTIVE {@see \App\Reports\ReportQuerySpec} (type,
 * resolved filters, grouping, sort) as JSON and the requested
 * {@see \App\Reports\ExportFormat}, so the queued job rebuilds the exact same
 * scoped, validated query the HTTP request resolved — the job never re-reads
 * the request. `requested_by` records the principal id for audit.
 *
 * Isolation is physical, so there is NO `tenant_id` column — an export row
 * written on the active tenant connection belongs to that tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();

            // The report type being exported (an allow-listed ReportType value).
            $table->string('report_type');

            // The requested export format: excel | csv | pdf (R29.4).
            $table->string('format');

            // The EFFECTIVE spec (defaults + scoping already applied) the queued
            // job rebuilds the query from, as JSON. Persisting the resolved spec
            // — not the raw request — guarantees the async job runs the exact
            // same scoped, validated query (R28.10, R29.2).
            $table->json('spec');

            // Lifecycle: pending -> processing -> completed | failed (R29.5).
            $table->string('status')->default('pending');

            // Number of rows written so far / in total once completed — surfaced
            // to the caller as progress.
            $table->unsignedBigInteger('row_count')->default(0);

            // The storage path and the public download link, populated only when
            // the export completes (R29.5).
            $table->string('file_path')->nullable();
            $table->string('download_url')->nullable();

            // When a failed export errored, the message for the caller.
            $table->text('error')->nullable();

            // The principal that requested the export (audit).
            $table->unsignedBigInteger('requested_by')->nullable();

            // When the export reached a terminal state.
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // (status): the worker/poller filters pending/processing exports.
            $table->index('status', 'report_exports_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
