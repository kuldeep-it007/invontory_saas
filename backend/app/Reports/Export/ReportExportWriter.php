<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Reports\ExportFormat;

/**
 * Writes a report's rows, streamed in, to a file in a given
 * {@see ExportFormat}.
 *
 * The export pipeline hands a writer the stream of resolved report rows (R29.5)
 * and the destination path; the writer is responsible for the format-specific
 * serialisation (Excel/CSV via PhpSpreadsheet, printable PDF). Writers consume
 * the rows as an iterable so a large export is appended chunk-by-chunk and never
 * materialises wholly in memory (R29.5).
 */
interface ReportExportWriter
{
    /**
     * The format this writer produces.
     */
    public function format(): ExportFormat;

    /**
     * Write the streamed rows to the absolute file path, returning the number
     * of data rows written. The first element of each row's keys forms the
     * header. An empty stream still produces a valid, header-only file.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @param  string  $absolutePath  Absolute destination file path.
     * @param  array<string, mixed>  $meta  Optional metadata (title, generated_at, date range) for the header area.
     */
    public function write(iterable $rows, string $absolutePath, array $meta = []): int;
}
