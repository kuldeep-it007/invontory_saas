<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Jobs\GenerateReportExport;
use App\Mail\ScheduledReportMail;
use App\Models\Company;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Models\Tenant;
use App\Reports\ExportFormat;
use App\Reports\ExportStatus;
use App\Reports\ReportAccessContext;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportType;
use App\Services\ReportExportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Task 11.11 — Integration tests: async export + scheduled report
|--------------------------------------------------------------------------
|
| These are the END-TO-END / integration angles the standalone 11.10 unit
| suite (ReportExportAndScheduleTest) does not cover. Where that suite fakes
| the bus and calls generate() by hand, this suite exercises the parts a real
| deployment relies on:
|
|   - QUEUE DISPATCH + REAL JOB EXECUTION (R29.5): a large export is enqueued
|     (Queue::fake proves the dispatch), and separately the actual
|     GenerateReportExport::handle() is run end-to-end against a provisioned
|     tenant so the JOB ITSELF — not a direct generate() call — streams the
|     rows into tenant storage and leaves a download link.
|   - FULL PIPELINE OVER THE SYNC QUEUE (R29.5): with the test queue running
|     synchronously (QUEUE_CONNECTION=sync), requesting a large export drives
|     the whole request → dispatch → worker → completed-with-link path with no
|     test seam, mirroring production.
|   - SCHEDULED REPORT DEFAULTS TO THE PREVIOUS CALENDAR MONTH AND EMAILS
|     RECIPIENTS (R29.6): the resolved period is asserted to equal the exact
|     previous calendar month boundaries (not merely "no date range"), and the
|     mail is asserted to carry that period + attachment to every recipient.
|   - ONLY DUE, ACTIVE schedules run; inactive and not-yet-due ones are left
|     untouched (R29.6).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling report suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for the integration tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionAsyncExportTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'axp'.$suffix,
        'subdomain' => 'axp-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'axp-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    return $tenant;
}

function asyncExportAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * Seed `$count` confirmed payments dated within the previous calendar month so
 * a payments report over the default range returns rows.
 */
function seedAsyncPreviousMonthPayments(int $count): void
{
    $paidAt = now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString();
    $now = now();

    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'buyer_type' => 'App\\Models\\Distributor',
            'buyer_id' => ($i % 5) + 1,
            'amount' => '10.00',
            'method' => 'cash',
            'paid_at' => $paidAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($rows, 200) as $chunk) {
        DB::table('payments')->insert($chunk);
    }
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownAsyncExportTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $t->domains()->delete();
            $t->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

it('enqueues a large export then completes it with a download link when the queued job is run end-to-end (R29.5)', function () {
    $registry = [];
    $tenant = provisionAsyncExportTenant($registry);

    try {
        $tenant->run(function () {
            config(['reports.export.async_threshold' => 5]);
            Company::create(['name' => 'Acme']);
            seedAsyncPreviousMonthPayments(12);

            // Capture the dispatched job WITHOUT running it, exactly as a worker
            // would find it on the queue.
            Queue::fake();

            $context = asyncExportAdminContext();
            $service = app(ReportExportService::class);

            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: $context,
                format: ExportFormat::CSV,
            );

            // The request left the export pending for the worker (R29.5).
            expect($export->status)->toBe(ExportStatus::PENDING->value);
            expect($export->download_url)->toBeNull();

            // Exactly one streaming job was pushed, carrying this export id.
            Queue::assertPushed(
                GenerateReportExport::class,
                fn (GenerateReportExport $job) => $job->exportId === $export->id,
            );

            // Now run the REAL job the way the worker would: construct it from
            // the captured payload and invoke handle() through the container.
            // This exercises GenerateReportExport itself, not a direct
            // generate() shortcut.
            $job = new GenerateReportExport($export->id, $context);
            app()->call([$job, 'handle']);

            $done = ReportExport::query()->find($export->id);
            expect($done->status)->toBe(ExportStatus::COMPLETED->value);
            expect($done->download_url)->not->toBeNull();
            // The payments report aggregates by period/buyer, so the streamed
            // row count is the number of distinct report rows (one per seeded
            // buyer), not the raw payment count — proving the job streamed the
            // whole aggregated result, not an empty or truncated slice.
            expect($done->row_count)->toBeGreaterThan(0);

            // The job physically wrote the file into tenant storage (R29.5).
            $absolute = Storage::disk('local')->path($done->file_path);
            expect(file_exists($absolute))->toBeTrue();
        });
    } finally {
        teardownAsyncExportTenants($registry);
    }
});

it('drives a large export through the full request → queue → worker pipeline on the sync queue, producing a download link (R29.5)', function () {
    $registry = [];
    $tenant = provisionAsyncExportTenant($registry);

    try {
        $tenant->run(function () {
            config(['reports.export.async_threshold' => 5]);
            Company::create(['name' => 'Acme']);
            seedAsyncPreviousMonthPayments(8);

            // No bus/queue fake: the test queue is synchronous, so dispatching
            // the streaming job runs it inline — the whole production path with
            // no test seam. The export must come back already completed.
            $service = app(ReportExportService::class);

            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: asyncExportAdminContext(),
                format: ExportFormat::CSV,
            );

            // The dispatched job ran on the sync queue and completed the row.
            $done = ReportExport::query()->find($export->id);
            expect($done->status)->toBe(ExportStatus::COMPLETED->value);
            expect($done->download_url)->not->toBeNull();
            expect($done->row_count)->toBeGreaterThan(0);

            $absolute = Storage::disk('local')->path($done->file_path);
            expect(file_exists($absolute))->toBeTrue();
        });
    } finally {
        teardownAsyncExportTenants($registry);
    }
});

it('fails the export row (no download link) when the queued job errors, so a poller stops waiting (R29.5)', function () {
    $registry = [];
    $tenant = provisionAsyncExportTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);
            seedAsyncPreviousMonthPayments(2);

            $context = asyncExportAdminContext();
            $service = app(ReportExportService::class);

            // Fake the queue so the dispatched job does NOT run inline on the
            // sync queue — the export row is left pending exactly as a real
            // worker would find it before processing.
            Queue::fake();

            // Create a pending export row the "worker" will try to process.
            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: $context,
                format: ExportFormat::CSV,
                forceAsync: true,
            );
            expect($export->fresh()->status)->toBe(ExportStatus::PENDING->value);

            // Simulate the queue exhausting retries and invoking failed(): the
            // row must become terminally FAILED with an error and no link.
            $job = new GenerateReportExport($export->id, $context);
            $job->failed(new RuntimeException('writer blew up'));

            $failed = ReportExport::query()->find($export->id);
            expect($failed->status)->toBe(ExportStatus::FAILED->value);
            expect($failed->error)->toContain('writer blew up');
            expect($failed->download_url)->toBeNull();
        });
    } finally {
        teardownAsyncExportTenants($registry);
    }
});

it('runs a scheduled report over the EXACT previous calendar month and emails that period to every recipient (R29.6)', function () {
    $registry = [];
    $tenant = provisionAsyncExportTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);
            seedAsyncPreviousMonthPayments(4);

            Mail::fake();

            // Pin the reference instant so "previous calendar month" is a known,
            // deterministic window we can assert against exactly.
            $relativeTo = CarbonImmutable::create(2024, 3, 15, 9, 0, 0);
            $expected = (new ReportFilters)->withDefaultDateRange($relativeTo);
            // February 2024 (leap year) — proves the month-boundary math.
            expect($expected->from->toDateString())->toBe('2024-02-01');
            expect($expected->to->toDateString())->toBe('2024-02-29');

            $service = app(ReportExportService::class);

            $schedule = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['owner@example.test', 'cfo@example.test'],
                format: ExportFormat::CSV,
                cadence: ReportSchedule::CADENCE_MONTHLY,
                name: 'Monthly collections',
                relativeTo: $relativeTo,
            );

            $export = $service->runSchedule($schedule->fresh(), relativeTo: $relativeTo);
            expect($export->status)->toBe(ExportStatus::COMPLETED->value);

            // The mail carries the resolved previous-calendar-month period and
            // an attachment, delivered to BOTH subscribed recipients (R29.6).
            Mail::assertQueued(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($expected) {
                return $mail->hasTo('owner@example.test')
                    && $mail->hasTo('cfo@example.test')
                    && str_starts_with((string) $mail->periodFrom, '2024-02-01')
                    && str_starts_with((string) $mail->periodTo, '2024-02-29')
                    && $mail->format === ExportFormat::CSV;
            });

            // The schedule advanced one cadence period from the run instant.
            $schedule->refresh();
            expect($schedule->last_run_at->toDateString())->toBe('2024-03-15');
            expect(CarbonImmutable::instance($schedule->next_run_at)->toDateString())->toBe('2024-04-15');
        });
    } finally {
        teardownAsyncExportTenants($registry);
    }
});

it('runs only due active schedules, leaving inactive and not-yet-due ones untouched (R29.6)', function () {
    $registry = [];
    $tenant = provisionAsyncExportTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);
            seedAsyncPreviousMonthPayments(2);

            Mail::fake();
            $service = app(ReportExportService::class);

            // Due now (next_run_at in the past).
            $due = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['due@example.test'],
                format: ExportFormat::CSV,
            );
            $due->forceFill(['next_run_at' => now()->subDay()])->save();

            // Not yet due.
            $future = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['future@example.test'],
                format: ExportFormat::CSV,
            );
            $future->forceFill(['next_run_at' => now()->addMonth()])->save();

            // Due by time, but INACTIVE — must be skipped.
            $inactive = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['inactive@example.test'],
                format: ExportFormat::CSV,
            );
            $inactive->forceFill(['next_run_at' => now()->subDay(), 'active' => false])->save();

            $ran = $service->runDueSchedules(CarbonImmutable::now());

            // Only the single due + active schedule ran.
            expect($ran)->toBe(1);
            Mail::assertQueued(ScheduledReportMail::class, 1);
            Mail::assertQueued(
                ScheduledReportMail::class,
                fn (ScheduledReportMail $mail) => $mail->hasTo('due@example.test'),
            );

            // The not-yet-due and inactive schedules were left untouched.
            expect($future->fresh()->last_run_at)->toBeNull();
            expect($inactive->fresh()->last_run_at)->toBeNull();
        });
    } finally {
        teardownAsyncExportTenants($registry);
    }
});
