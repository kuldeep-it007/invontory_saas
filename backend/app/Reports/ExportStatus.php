<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * The lifecycle of an asynchronous report export (R29.5).
 *
 * A large export is processed as a queued job that streams rows and provides a
 * download link on completion (R29.5). The export row moves through:
 *
 *   PENDING    -> queued, not yet picked up by a worker
 *   PROCESSING -> a worker is streaming rows into the file
 *   COMPLETED  -> the file is written and a download link is available
 *   FAILED     -> the job errored; no download link
 *
 * The download link is populated only in the COMPLETED state, so a caller
 * polling the export knows the file is ready precisely when
 * {@see self::isTerminal()} is true and the status is COMPLETED.
 */
enum ExportStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * Whether this is a terminal state (the job will not transition again). A
     * caller polling an export stops once it observes a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }
}
