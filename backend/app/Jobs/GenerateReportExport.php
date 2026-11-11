<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportExport;
use App\Reports\ReportAccessContext;
use App\Services\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued streaming job that produces a large report export off the request
 * cycle and marks the export row complete with a download link (R29.5).
 *
 * When an export is large, the Report_Engine processes it as an asynchronous
 * queued job that streams rows and provides a download link on completion
 * (R29.5). This job carries only the {@see ReportExport} id and the resolved
 * access context: it reloads the export row, hands it to the
 * {@see ReportExportService} which streams the report's rows through the chosen
 * writer into the tenant's storage, then flips the row to `completed` with its
 * download URL — or `failed` on error.
 *
 * TENANT CONTEXT: the job is dispatched from inside tenant context, and the
 * `QueueTenancyBootstrapper` (config/tenancy.php) re-initialises the SAME
 * tenant when the worker runs it, so the export row, the report query, and the
 * tenant storage disk all resolve to the originating tenant automatically — no
 * `tenant_id` is threaded through by hand (physical isolation).
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry the export a couple of times before giving up. */
    public int $tries = 3;

    public function __construct(
        public readonly int $exportId,
        public readonly ReportAccessContext $context,
    ) {}

    /**
     * Stream the report into the export file and complete the export row
     * (R29.5). Reloading the row (rather than serialising the whole model)
     * keeps the job payload small and ensures it operates on current state.
     */
    public function handle(ReportExportService $service): void
    {
        $export = ReportExport::query()->find($this->exportId);

        if ($export === null) {
            // The export row was removed before the worker ran; nothing to do.
            return;
        }

        $service->generate($export, $this->context);
    }

    /**
     * Record a terminal failure on the export row so a poller stops waiting
     * (R29.5). Called by the queue after the final retry.
     */
    public function failed(Throwable $e): void
    {
        $export = ReportExport::query()->find($this->exportId);

        if ($export !== null) {
            app(ReportExportService::class)->markFailed($export, $e->getMessage());
        }
    }
}
