<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Reports\ExportFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;

/**
 * Writes a report to Excel (.xlsx) or CSV via PhpSpreadsheet (R29.4).
 *
 * One writer serves both formats because PhpSpreadsheet builds one in-memory
 * worksheet and swaps only the concrete writer at save time. The header row is
 * derived from the first streamed row's keys; every subsequent row is appended
 * in column order so the output columns match the report's SELECTed columns
 * (R38.1). An empty result still yields a valid file with just the header
 * (when the report's column set is known) or an empty sheet.
 *
 * NOTE on streaming: a true row-at-a-time flush to disk requires a format that
 * supports append (CSV does; XLSX is a zipped package assembled at save). To
 * keep ONE consistent, correct code path for both formats, this writer builds
 * the sheet from the streamed rows and saves once. The upstream query streams
 * rows in bounded chunks (R29.5) so the generator never holds the whole result
 * set at once; PhpSpreadsheet's sheet is the single bounded buffer the save
 * step serialises. For CSV specifically, callers that must stream enormous
 * exports straight to disk should prefer {@see CsvStreamReportWriter}.
 */
class SpreadsheetReportWriter implements ReportExportWriter
{
    public function __construct(private readonly ExportFormat $format = ExportFormat::EXCEL)
    {
        if (! in_array($this->format, [ExportFormat::EXCEL, ExportFormat::CSV], true)) {
            throw new RuntimeException('SpreadsheetReportWriter supports only excel and csv formats.');
        }
    }

    public function format(): ExportFormat
    {
        return $this->format;
    }

    public function write(iterable $rows, string $absolutePath, array $meta = []): int
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr((string) ($meta['title'] ?? 'Report'), 0, 31));

        $header = null;
        $rowIndex = 1;
        $written = 0;

        foreach ($rows as $row) {
            if ($header === null) {
                $header = array_keys($row);
                $this->writeLine($sheet, $rowIndex++, $header);
            }

            $ordered = [];
            foreach ($header as $key) {
                $ordered[] = $this->scalar($row[$key] ?? null);
            }
            $this->writeLine($sheet, $rowIndex++, $ordered);
            $written++;
        }

        $this->ensureDirectory($absolutePath);

        $writer = $this->format === ExportFormat::CSV
            ? new CsvWriter($spreadsheet)
            : new XlsxWriter($spreadsheet);

        $writer->save($absolutePath);

        // Free the in-memory sheet promptly for the next (possibly batched) run.
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $written;
    }

    /**
     * Write one row of cell values starting at column A of the given 1-based
     * row.
     *
     * @param  list<string|int|float|null>  $values
     */
    private function writeLine($sheet, int $rowIndex, array $values): void
    {
        $col = 1;
        foreach ($values as $value) {
            $sheet->setCellValueExplicit(
                [$col, $rowIndex],
                (string) ($value ?? ''),
                DataType::TYPE_STRING,
            );
            $col++;
        }
    }

    /**
     * Coerce a report cell to a spreadsheet-safe scalar. Arrays/objects are
     * JSON-encoded so a nested value never breaks the row shape.
     */
    private function scalar(mixed $value): string|int|float|null
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        return json_encode($value);
    }

    private function ensureDirectory(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
