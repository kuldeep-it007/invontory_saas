<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Roles;
use App\Jobs\GenerateReportExport;
use App\Mail\ScheduledReportMail;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Reports\Export\ReportExportWriterFactory;
use App\Reports\ExportFormat;
use App\Reports\ExportStatus;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders reports to downloadable files and runs scheduled/subscription reports
 * — the export + scheduling half of the Report_Engine (R29.4, R29.5, R29.6).
 *
 * It builds ON TOP of the single shared {@see ReportQueryService}: every export
 * and every scheduled run streams its rows through that one query path, so an
 * export inherits the exact same previous-month default (R29.2), tenant+role
 * scoping (R28.10), and allow-list validation (R31.2) a paginated read gets. An
 * export can therefore never see data the caller could not read interactively.
 *
 * The three capabilities, each tied to its requirement:
 *
 *   - EXPORT (R29.4): {@see export()} renders a report to Excel, CSV, or a
 *     printable PDF via the {@see ReportExportWriterFactory}. The HTTP surface
 *     mounts this behind the `exports` feature flag, so the format set is only
 *     reachable when the tenant's plan enables exports.
 *   - LARGE / ASYNC (R29.5): a large export is NOT produced inline — a
 *     `report_exports` row is created in the `pending` state and a
 *     {@see GenerateReportExport} queued job streams the rows into the tenant
 *     storage disk and flips the row to `completed` with a download link. A
 *     small export is produced inline for immediate download. "Large" is a
 *     configurable row-count threshold.
 *   - SCHEDULED (R29.6): {@see runSchedule()} and {@see runDueSchedules()}
 *     execute a subscription report DEFAULTING to the previous calendar month
 *     (the stored spec carries no date range on purpose) and email the rendered
 *     file to the subscribed recipients via {@see ScheduledReportMail}.
 *
 * Produced files live on the tenant-scoped storage disk (the stancl filesystem
 * bootstrapper roots each tenant's storage separately), so one tenant's export
 * is never reachable from another — isolation stays physical.
 */
class ReportExportService
{
    /**
     * Rows at or above this count make an export "large" and route it to the
     * async queued job (R29.5). Below it, the export is produced inline for an
     * immediate download link. Configurable via `reports.export.async_threshold`.
     */
    public const DEFAULT_ASYNC_THRESHOLD = 1000;

    /** The storage disk exports are written to (tenant-scoped by stancl). */
    private const DISK = 'local';

    /** The directory within the disk exports live under. */
    private const DIRECTORY = 'exports/reports';

    public function __construct(
        private readonly ReportQueryService $reports,
        private readonly ReportExportWriterFactory $writers,
    ) {}

    /**
     * Request an export of a report in a given format (R29.4, R29.5).
     *
     * Resolves the spec through the query layer (so defaults + scoping +
     * allow-listing all apply), records a `report_exports` row, then either:
     *
     *   - dispatches a {@see GenerateReportExport} queued job when the result is
     *     large (>= the async threshold), leaving the row `pending` for a worker
     *     to stream and complete (R29.5); or
     *   - produces the file inline and returns the row already `completed` with
     *     a download link, for a small export.
     *
     * The returned {@see ReportExport} is the handle the caller polls (its
     * `status` and `download_url`) to retrieve the finished file.
     *
     * @param  bool|null  $forceAsync  Force async regardless of size (used by schedules/large requests); null = decide by size.
     */
    public function export(
        ReportQuerySpec $spec,
        ReportAccessContext $context,
        ExportFormat $format,
        ?int $requestedBy = null,
        ?bool $forceAsync = null,
        ?CarbonImmutable $relativeTo = null,
    ): ReportExport {
        // Resolve the spec exactly as a read would (R29.2, R28.10, R31.2); this
        // also validates the type/columns up front so a bad request fails before
        // an export row is created.
        [$resolvedSpec] = $this->reports->resolveQuery($spec, $context, $relativeTo);

        $export = ReportExport::query()->create([
            'report_type' => $resolvedSpec->type->value,
            'format' => $format->value,
            'spec' => $this->specToArray($resolvedSpec),
            'status' => ExportStatus::PENDING->value,
            'row_count' => 0,
            'requested_by' => $requestedBy,
        ]);

        $async = $forceAsync ?? $this->shouldRunAsync($resolvedSpec, $context);

        if ($async) {
            // Large export: stream it off-cycle; the worker completes the row
            // and populates the download link (R29.5).
            GenerateReportExport::dispatch($export->id, $context);

            return $export->refresh();
        }

        // Small export: produce inline so the caller gets an immediate link.
        $this->generate($export, $context);

        return $export->refresh();
    }

    /**
     * Produce the export file for a `report_exports` row by streaming the
     * report's rows through the format writer into tenant storage, then mark the
     * row `completed` with its download link (R29.5). Any failure marks the row
     * `failed` and rethrows so the queue can retry/record it.
     *
     * This is the single rendering path both the inline branch of
     * {@see export()} and the {@see GenerateReportExport} queued job call, so a
     * small and a large export produce byte-identical files.
     */
    public function generate(ReportExport $export, ReportAccessContext $context): ReportExport
    {
        $export->update(['status' => ExportStatus::PROCESSING->value]);

        try {
            $spec = ReportQuerySpec::fromArray($export->spec);
            $format = $export->format();
            $writer = $this->writers->for($format);

            $relativePath = $this->filePathFor($export, $format);
            $disk = $this->disk();
            $absolutePath = $this->absolutePath($disk, $relativePath);

            // Stream rows from the shared query layer and write them straight
            // into the file — the result never materialises wholly in memory
            // (R29.5). The spec is already resolved (defaults + scoping applied
            // when the export was requested), so re-running it here is a no-op
            // on those rules and simply re-reads the same scoped data.
            $meta = $this->metaFor($export, $spec);
            $rowCount = $writer->write(
                $this->reports->stream($spec, $context),
                $absolutePath,
                $meta,
            );

            $downloadUrl = $this->downloadUrl($disk, $relativePath);

            $export->update([
                'status' => ExportStatus::COMPLETED->value,
                'row_count' => $rowCount,
                'file_path' => $relativePath,
                'download_url' => $downloadUrl,
                'completed_at' => now(),
                'error' => null,
            ]);

            return $export->refresh();
        } catch (Throwable $e) {
            $this->markFailed($export, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Mark an export terminally failed with a message (R29.5), so a poller
     * stops waiting. Safe to call from the job's `failed()` hook.
     */
    public function markFailed(ReportExport $export, string $message): void
    {
        $export->update([
            'status' => ExportStatus::FAILED->value,
            'error' => $message,
            'completed_at' => now(),
        ]);
    }

    /**
     * Create a scheduled/subscription report (R29.6).
     *
     * The stored spec is persisted WITHOUT a date range on purpose, so every
     * run defaults to the previous calendar month (R29.2, R29.6). The schedule's
     * first `next_run_at` is seeded from now by its cadence.
     *
     * @param  list<string>  $recipients  Recipient email addresses (R29.6).
     * @param  array<string, mixed>  $specInput  Report spec fragment (type/filters/grouping/sort) — any date range is stripped.
     */
    public function schedule(
        ReportType $type,
        array $recipients,
        ExportFormat $format = ExportFormat::EXCEL,
        string $cadence = ReportSchedule::CADENCE_MONTHLY,
        array $specInput = [],
        ?string $name = null,
        ?int $createdBy = null,
        ?CarbonImmutable $relativeTo = null,
    ): ReportSchedule {
        $spec = $this->strippedSpec($type, $specInput);
        $cadence = in_array($cadence, ReportSchedule::CADENCES, true) ? $cadence : ReportSchedule::CADENCE_MONTHLY;

        $reference = $relativeTo ?? CarbonImmutable::now();

        $schedule = new ReportSchedule([
            'name' => $name,
            'report_type' => $type->value,
            'format' => $format->value,
            'spec' => $spec,
            'recipients' => array_values(array_filter($recipients, static fn ($r): bool => is_string($r) && $r !== '')),
            'cadence' => $cadence,
            'active' => true,
            'created_by' => $createdBy,
        ]);

        // Seed next_run_at one cadence period out from the reference.
        $schedule->next_run_at = $schedule->advanceSchedule($reference);
        $schedule->save();

        return $schedule;
    }

    /**
     * Run a single scheduled report now (R29.6): execute the report defaulting
     * to the previous calendar month and email the rendered file to the
     * subscribed recipients. Advances the schedule's `next_run_at` and records
     * `last_run_at`.
     *
     * @param  ReportAccessContext|null  $context  The scope the report runs under; defaults to tenant-wide (a schedule is a tenant-admin artefact).
     */
    public function runSchedule(ReportSchedule $schedule, ?ReportAccessContext $context = null, ?CarbonImmutable $relativeTo = null): ReportExport
    {
        $context ??= $this->tenantWideContext();
        $format = $schedule->format();

        // The stored spec has NO date range, so resolving it defaults to the
        // previous calendar month (R29.2, R29.6).
        $spec = ReportQuerySpec::fromArray($schedule->spec + ['type' => $schedule->report_type]);

        // Scheduled exports always render synchronously here (the dispatcher is
        // already off the request cycle) so the file exists to attach to the
        // email.
        $export = $this->export(
            spec: $spec,
            context: $context,
            format: $format,
            requestedBy: $schedule->created_by,
            forceAsync: false,
            relativeTo: $relativeTo,
        );

        $this->emailSchedule($schedule, $export, $spec, $relativeTo);

        $ranAt = $relativeTo ?? CarbonImmutable::now();
        $schedule->forceFill([
            'last_run_at' => $ranAt,
            'next_run_at' => $schedule->advanceSchedule($ranAt),
        ])->save();

        return $export;
    }

    /**
     * Run every active schedule whose `next_run_at` has passed (R29.6). Returns
     * the number of schedules run. Called by the scheduler/dispatcher.
     */
    public function runDueSchedules(?CarbonImmutable $now = null, ?ReportAccessContext $context = null): int
    {
        $now ??= CarbonImmutable::now();

        $due = ReportSchedule::query()
            ->where('active', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->get();

        foreach ($due as $schedule) {
            $this->runSchedule($schedule, $context, $now);
        }

        return $due->count();
    }

    /**
     * Email a completed scheduled export to its recipients (R29.6). Skips
     * delivery when there are no recipients or the file is not available.
     */
    private function emailSchedule(ReportSchedule $schedule, ReportExport $export, ReportQuerySpec $spec, ?CarbonImmutable $relativeTo): void
    {
        $recipients = $schedule->recipients ?? [];
        if ($recipients === [] || $export->file_path === null) {
            return;
        }

        $absolutePath = $this->absolutePath($this->disk(), $export->file_path);

        $mail = new ScheduledReportMail(
            reportLabel: $schedule->name ?? $schedule->report_type,
            reportType: $schedule->report_type,
            filePath: $absolutePath,
            format: $schedule->format(),
            periodFrom: $spec->filters->withDefaultDateRange($relativeTo)->from?->toIso8601String(),
            periodTo: $spec->filters->withDefaultDateRange($relativeTo)->to?->toIso8601String(),
            rowCount: $export->row_count,
        );

        Mail::to($recipients)->queue($mail);
    }

    /**
     * Decide whether an export is large enough to run asynchronously (R29.5).
     * Counts the resolved result's rows and compares against the configured
     * threshold.
     */
    private function shouldRunAsync(ReportQuerySpec $spec, ReportAccessContext $context): bool
    {
        $threshold = (int) config('reports.export.async_threshold', self::DEFAULT_ASYNC_THRESHOLD);

        [, $query] = $this->reports->resolveQuery($spec, $context);

        // A grouped query must be counted by its group rows; reuse a bounded
        // count that stops as soon as the threshold is reached so we never scan
        // an entire huge table just to decide.
        $count = (clone $query)->limit($threshold)->get()->count();

        return $count >= $threshold;
    }

    /**
     * Build the file metadata (title + period + generated time) handed to the
     * writer for the document header area.
     *
     * @return array<string, mixed>
     */
    private function metaFor(ReportExport $export, ReportQuerySpec $spec): array
    {
        return [
            'title' => $export->report_type,
            'generated_at' => now()->toIso8601String(),
            'date_from' => $spec->filters->from?->toIso8601String(),
            'date_to' => $spec->filters->to?->toIso8601String(),
        ];
    }

    /**
     * The storage-relative path a given export's file is written to. Namespaced
     * by export id + format extension so files never collide.
     */
    private function filePathFor(ReportExport $export, ExportFormat $format): string
    {
        return sprintf('%s/report-%d-%s.%s', self::DIRECTORY, $export->id, $export->report_type, $format->extension());
    }

    /**
     * Resolve a stored spec to the array shape {@see ReportQuerySpec::fromArray()}
     * reconstructs, preserving the resolved filters, grouping, and sort.
     *
     * @return array<string, mixed>
     */
    private function specToArray(ReportQuerySpec $spec): array
    {
        return [
            'type' => $spec->type->value,
            'filters' => [
                'from' => $spec->filters->from?->toIso8601String(),
                'to' => $spec->filters->to?->toIso8601String(),
                'buyerType' => $spec->filters->buyerType,
                'buyerId' => $spec->filters->buyerId,
                'sellerType' => $spec->filters->sellerType,
                'sellerId' => $spec->filters->sellerId,
                'productId' => $spec->filters->productId,
                'categoryId' => $spec->filters->categoryId,
                'stockPointId' => $spec->filters->stockPointId,
                'status' => $spec->filters->status,
            ],
            'grouping' => $spec->grouping,
            'sort' => array_map(
                static fn ($s): array => ['column' => $s->column, 'dir' => $s->isDescending() ? 'desc' : 'asc'],
                $spec->sort,
            ),
            'limit' => $spec->limit,
            'page' => $spec->page,
        ];
    }

    /**
     * Build a schedule's stored spec from input, STRIPPING any date range so
     * each run defaults to the previous calendar month (R29.2, R29.6).
     *
     * @param  array<string, mixed>  $specInput
     * @return array<string, mixed>
     */
    private function strippedSpec(ReportType $type, array $specInput): array
    {
        $filters = is_array($specInput['filters'] ?? null) ? $specInput['filters'] : [];
        unset($filters['from'], $filters['to'], $filters['dateFrom'], $filters['dateTo']);

        return [
            'type' => $type->value,
            'filters' => $filters,
            'grouping' => array_values((array) ($specInput['grouping'] ?? [])),
            'sort' => array_values((array) ($specInput['sort'] ?? [])),
        ];
    }

    /**
     * A tenant-wide access context for scheduled runs — a subscription report
     * is a tenant-admin artefact that reads tenant-wide data (R28.10), confined
     * physically to the active tenant database.
     */
    private function tenantWideContext(): ReportAccessContext
    {
        return new ReportAccessContext(
            roles: [Roles::COMPANY_ADMIN],
            canViewReports: true,
        );
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Resolve the absolute filesystem path for a disk-relative path, creating
     * the directory as needed. Works for local disks (the default); for a cloud
     * disk the writer streams through a temp file first.
     */
    private function absolutePath(Filesystem $disk, string $relativePath): string
    {
        if (method_exists($disk, 'path')) {
            $path = $disk->path($relativePath);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            return $path;
        }

        // Fallback for non-local disks: write to a temp file (the caller may
        // later putStream it to the disk). Kept simple as the default disk is
        // local.
        return tempnam(sys_get_temp_dir(), 'report-export-');
    }

    /**
     * The download link for a completed export (R29.5). Uses the disk's URL
     * when available (public disk); otherwise a route-less storage-relative
     * reference the API exposes through a signed download endpoint.
     */
    private function downloadUrl(Filesystem $disk, string $relativePath): string
    {
        try {
            if (method_exists($disk, 'url')) {
                return $disk->url($relativePath);
            }
        } catch (Throwable) {
            // Disk has no URL generator; fall through to the relative path.
        }

        return $relativePath;
    }
}
