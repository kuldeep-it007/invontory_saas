<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `report_schedules` table (R29.6).
 *
 * A scheduled or subscription report: when it is due, the Report_Engine runs
 * the report defaulting the date range to the previous calendar month and
 * emails the result to the subscribed recipients (R29.6).
 *
 * Each row carries the report `type`, the stored {@see \App\Reports\ReportFilters}
 * and grouping/sort (as JSON) — WITHOUT a date range, so every run inherits the
 * previous-calendar-month default from the shared query layer (R29.2, R29.6) —
 * the export `format` emailed to recipients, the recipient email list, the
 * cadence, and the next due timestamp the scheduler compares against.
 *
 * Isolation is physical, so there is NO `tenant_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();

            // A human label for the subscription (e.g. "Monthly sales").
            $table->string('name')->nullable();

            // The report type the schedule runs (an allow-listed ReportType).
            $table->string('report_type');

            // The export format emailed to recipients: excel | csv | pdf (R29.4).
            $table->string('format')->default('excel');

            // Stored spec fragment (filters WITHOUT a date range, grouping,
            // sort) the scheduled run rebuilds from. The date range is left
            // unset ON PURPOSE so each run defaults to the previous calendar
            // month (R29.2, R29.6).
            $table->json('spec');

            // Recipient email addresses the completed report is emailed to
            // (R29.6).
            $table->json('recipients');

            // Cadence: how often the schedule is due (daily | weekly | monthly).
            $table->string('cadence')->default('monthly');

            // Whether the schedule is currently active.
            $table->boolean('active')->default(true);

            // The next instant the schedule is due; the dispatcher runs every
            // schedule whose next_run_at has passed, then advances it.
            $table->timestamp('next_run_at')->nullable();

            // The last time the schedule ran (audit / idempotency).
            $table->timestamp('last_run_at')->nullable();

            // The principal that created the subscription (audit).
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // (active, next_run_at): the dispatcher's "due schedules" scan.
            $table->index(['active', 'next_run_at'], 'report_schedules_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
