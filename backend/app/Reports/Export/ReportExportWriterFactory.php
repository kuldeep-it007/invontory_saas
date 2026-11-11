<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Reports\ExportFormat;
use App\Services\ReportExportService;
use InvalidArgumentException;

/**
 * Resolves the concrete {@see ReportExportWriter} for a requested
 * {@see ExportFormat} (R29.4).
 *
 * Centralising the format → writer mapping keeps the
 * {@see ReportExportService} agnostic of writer construction and
 * gives one place to swap a writer (e.g. a true streaming CSV vs the
 * spreadsheet CSV) without touching the service. CSV uses the row-at-a-time
 * {@see CsvStreamReportWriter} so a large CSV export streams to disk (R29.5);
 * Excel uses {@see SpreadsheetReportWriter}; PDF uses
 * {@see PrintablePdfReportWriter}.
 */
class ReportExportWriterFactory
{
    /**
     * Resolve the writer for a format.
     *
     * @throws InvalidArgumentException when the format has no writer.
     */
    public function for(ExportFormat $format): ReportExportWriter
    {
        return match ($format) {
            ExportFormat::EXCEL => new SpreadsheetReportWriter(ExportFormat::EXCEL),
            ExportFormat::CSV => new CsvStreamReportWriter,
            ExportFormat::PDF => new PrintablePdfReportWriter,
        };
    }
}
