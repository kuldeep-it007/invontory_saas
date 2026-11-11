<?php

declare(strict_types=1);

namespace App\Reports;

use App\Services\ReportExportService;

/**
 * The closed set of export formats a report may be rendered to when the
 * `exports` Feature_Flag is enabled for the tenant (R29.4).
 *
 * R29.4 requires exactly three formats — Excel, CSV, and a printable PDF — so
 * this enum is the single allow-list of export `format` values the
 * {@see ReportExportService} and the HTTP surface validate a
 * requested format against. Keeping the set here (rather than as free-form
 * strings) means an unknown format is rejected in one place before any file is
 * produced, exactly as {@see ReportType} does for report types.
 */
enum ExportFormat: string
{
    /** Microsoft Excel workbook (.xlsx) via PhpSpreadsheet (R29.4). */
    case EXCEL = 'excel';

    /** Comma-separated values (.csv) via PhpSpreadsheet's CSV writer (R29.4). */
    case CSV = 'csv';

    /** Printable PDF rendered from an HTML report view (R29.4). */
    case PDF = 'pdf';

    /**
     * Resolve a format from its wire value, returning null when the value is
     * not an allow-listed format. The HTTP layer uses this to reject an unknown
     * `format` BEFORE an export is queued (R29.4).
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * All export-format wire values as a flat list — the allow-list a requested
     * `format` is checked against.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * The file extension (without the leading dot) a rendered export of this
     * format is written with.
     */
    public function extension(): string
    {
        return match ($this) {
            self::EXCEL => 'xlsx',
            self::CSV => 'csv',
            self::PDF => 'pdf',
        };
    }

    /**
     * The MIME content type served when the finished export is downloaded.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::EXCEL => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CSV => 'text/csv',
            self::PDF => 'application/pdf',
        };
    }
}
