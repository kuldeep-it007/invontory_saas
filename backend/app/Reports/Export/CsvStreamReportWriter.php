<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Reports\ExportFormat;
use RuntimeException;

/**
 * Streams a report straight to a CSV file one row at a time (R29.4, R29.5).
 *
 * Unlike {@see SpreadsheetReportWriter}, this writer opens the destination file
 * and appends each streamed row immediately with `fputcsv`, so an arbitrarily
 * large export is written with O(1) memory — the single most important property
 * for the "streams rows" requirement of a large async export (R29.5). The
 * header is taken from the first row's keys; an empty stream produces an empty
 * file.
 */
class CsvStreamReportWriter implements ReportExportWriter
{
    public function format(): ExportFormat
    {
        return ExportFormat::CSV;
    }

    public function write(iterable $rows, string $absolutePath, array $meta = []): int
    {
        $this->ensureDirectory($absolutePath);

        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open export file for writing: {$absolutePath}");
        }

        $header = null;
        $written = 0;

        try {
            foreach ($rows as $row) {
                if ($header === null) {
                    $header = array_keys($row);
                    fputcsv($handle, $header);
                }

                $ordered = [];
                foreach ($header as $key) {
                    $ordered[] = $this->scalar($row[$key] ?? null);
                }
                fputcsv($handle, $ordered);
                $written++;
            }
        } finally {
            fclose($handle);
        }

        return $written;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }

    private function ensureDirectory(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
