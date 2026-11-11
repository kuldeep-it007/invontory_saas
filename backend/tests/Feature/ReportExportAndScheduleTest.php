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
use App\Reports\ReportQuerySpec;
use App\Reports\ReportType;
use App\Services\ReportExportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Task 11.10 / 11.11 — Async export + scheduled report (DB-gated, end-to-end)
|--------------------------------------------------------------------------
|
| Exercises the export + scheduling half of the Report_Engine against a REAL
| provisioned tenant database:
|
|   - A small export is produced inline and completes with a download link
|     (R29.4, R29.5).
|   - A large export (above the async threshold) is dispatched as a queued
|     streaming job rather than produced inline (R29.5), and running the job
|     streams the rows into tenant storage and completes the export with a
|     download link (R29.5).
|   - A scheduled report runs defaulting to the previous calendar month and
|     emails the subscribed recipients (R29.6).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling report suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for export tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionExportTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'exp'.$suffix,
        'subdomain' => 'exp-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'exp-'.$suffix.'.example.test']);
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

function exportAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * Seed `$count` confirmed payments dated within the previous calendar month so
 * a payments report over the default range returns rows.
 */
function seedPreviousMonthPayments(int $count): void
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
function teardownExportTenants(array $registry): void
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

it('produces a small export inline and completes it with a download link (R29.4, R29.5)', function () {
    $registry = [];
    $tenant = provisionExportTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);
            seedPreviousMonthPayments(3);

            $service = app(ReportExportService::class);

            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: exportAdminContext(),
                format: ExportFormat::CSV,
            );

            expect($export->status)->toBe(ExportStatus::COMPLETED->value);
            expect($export->download_url)->not->toBeNull();
            expect($export->row_count)->toBeGreaterThan(0);

            // The file physically exists on the tenant storage disk.
            $absolute = Storage::disk('local')->path($export->file_path);
            expect(file_exists($absolute))->toBeTrue();
        });
    } finally {
        teardownExportTenants($registry);
    }
});

it('dispatches a large export as a queued streaming job rather than inline (R29.5)', function () {
    $registry = [];
    $tenant = provisionExportTenant($registry);

    try {
        $tenant->run(function () {
            config(['reports.export.async_threshold' => 5]);
            Company::create(['name' => 'Acme']);
            // 10 rows >= threshold 5 → large → async.
            seedPreviousMonthPayments(10);

            Bus::fake();

            $service = app(ReportExportService::class);
            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: exportAdminContext(),
                format: ExportFormat::EXCEL,
            );

            // The export is left pending for the worker; the job is queued.
            expect($export->status)->toBe(ExportStatus::PENDING->value);
            Bus::assertDispatched(GenerateReportExport::class, fn ($job) => $job->exportId === $export->id);
        });
    } finally {
        teardownExportTenants($registry);
    }
});

it('streams a large export to completion when the queued job runs, exposing a download link (R29.5)', function () {
    $registry = [];
    $tenant = provisionExportTenant($registry);

    try {
        $tenant->run(function () {
            config(['reports.export.async_threshold' => 5]);
            Company::create(['name' => 'Acme']);
            seedPreviousMonthPayments(12);

            $service = app(ReportExportService::class);

            // Fake the bus so the dispatched job does NOT run inline; the export
            // is left pending exactly as a real worker would find it.
            Bus::fake();
            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: exportAdminContext(),
                format: ExportFormat::CSV,
                forceAsync: true,
            );
            expect($export->status)->toBe(ExportStatus::PENDING->value);
            Bus::assertDispatched(GenerateReportExport::class);

            // Run the generation step the queued job would run (R29.5).
            $service->generate($export->fresh(), exportAdminContext());

            $done = ReportExport::query()->find($export->id);
            expect($done->status)->toBe(ExportStatus::COMPLETED->value);
            expect($done->download_url)->not->toBeNull();
            expect($done->row_count)->toBeGreaterThan(0);
        });
    } finally {
        teardownExportTenants($registry);
    }
});

it('runs a scheduled report over the previous calendar month and emails recipients (R29.6)', function () {
    $registry = [];
    $tenant = provisionExportTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);
            seedPreviousMonthPayments(4);

            Mail::fake();

            $service = app(ReportExportService::class);

            $schedule = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['owner@example.test', 'cfo@example.test'],
                format: ExportFormat::CSV,
                cadence: ReportSchedule::CADENCE_MONTHLY,
                name: 'Monthly collections',
            );

            // The stored spec carries no date range so each run defaults to the
            // previous calendar month (R29.2, R29.6).
            expect($schedule->spec['filters'])->not->toHaveKey('from');
            expect($schedule->spec['filters'])->not->toHaveKey('to');

            $export = $service->runSchedule($schedule->fresh());

            // The run defaulted to the previous calendar month and found data.
            expect($export->status)->toBe(ExportStatus::COMPLETED->value);
            expect($export->row_count)->toBeGreaterThan(0);

            // The report was emailed to both subscribed recipients (R29.6).
            Mail::assertQueued(ScheduledReportMail::class, function (ScheduledReportMail $mail) {
                return $mail->hasTo('owner@example.test') && $mail->hasTo('cfo@example.test');
            });

            // The schedule advanced for its next run.
            $schedule->refresh();
            expect($schedule->last_run_at)->not->toBeNull();
            expect($schedule->next_run_at)->not->toBeNull();
        });
    } finally {
        teardownExportTenants($registry);
    }
});

it('runs only due active schedules (R29.6)', function () {
    $registry = [];
    $tenant = provisionExportTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);
            seedPreviousMonthPayments(2);

            Mail::fake();
            $service = app(ReportExportService::class);

            // Due now.
            $due = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['a@example.test'],
                format: ExportFormat::CSV,
            );
            $due->forceFill(['next_run_at' => now()->subDay()])->save();

            // Not yet due.
            $future = $service->schedule(
                type: ReportType::PAYMENTS,
                recipients: ['b@example.test'],
                format: ExportFormat::CSV,
            );
            $future->forceFill(['next_run_at' => now()->addMonth()])->save();

            $ran = $service->runDueSchedules(CarbonImmutable::now());

            expect($ran)->toBe(1);
            Mail::assertQueued(ScheduledReportMail::class, 1);
        });
    } finally {
        teardownExportTenants($registry);
    }
});
