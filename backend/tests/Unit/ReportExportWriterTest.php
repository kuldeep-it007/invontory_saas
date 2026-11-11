<?php

declare(strict_types=1);

use App\Reports\Export\CsvStreamReportWriter;
use App\Reports\Export\PrintablePdfReportWriter;
use App\Reports\Export\ReportExportWriterFactory;
use App\Reports\Export\SpreadsheetReportWriter;
use App\Reports\ExportFormat;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
|--------------------------------------------------------------------------
| Task 11.10 — Report export writers (R29.4)
|--------------------------------------------------------------------------
|
| Unit coverage of the three export formats R29.4 requires — Excel, CSV, and a
| printable PDF — plus the format allow-list enum and the writer factory. These
| are pure file-rendering checks: a known set of rows is streamed through each
| writer to a temp file and read back, so they need no database or booted
| tenancy and run everywhere.
|
*/

/**
 * A fresh temp file path with the given extension (not yet created).
 */
function tempExportPath(string $ext): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'report-export-test-'.bin2hex(random_bytes(6)).'.'.$ext;
}

/**
 * The sample rows every writer test streams.
 *
 * @return list<array<string, mixed>>
 */
function sampleExportRows(): array
{
    return [
        ['period' => '2024-01-01', 'buyer_id' => 7, 'sales_value' => 270.0],
        ['period' => '2024-01-02', 'buyer_id' => 8, 'sales_value' => 100.5],
        // A row with a comma + quote to exercise CSV escaping.
        ['period' => '2024-01-03', 'buyer_id' => 9, 'sales_value' => 'a,b"c'],
    ];
}

it('exposes exactly the three R29.4 export formats on the allow-list', function () {
    expect(ExportFormat::values())->toEqualCanonicalizing(['excel', 'csv', 'pdf']);
    expect(ExportFormat::tryFromValue('excel'))->toBe(ExportFormat::EXCEL);
    expect(ExportFormat::tryFromValue('json'))->toBeNull();

    expect(ExportFormat::EXCEL->extension())->toBe('xlsx');
    expect(ExportFormat::CSV->extension())->toBe('csv');
    expect(ExportFormat::PDF->extension())->toBe('pdf');
});

it('writes a CSV with a header and one line per row, escaping special characters (R29.4)', function () {
    $path = tempExportPath('csv');

    try {
        $written = (new CsvStreamReportWriter)->write(sampleExportRows(), $path);

        expect($written)->toBe(3);
        expect(file_exists($path))->toBeTrue();

        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', file_get_contents($path))), fn ($l) => $l !== ''));
        // Header + 3 data rows.
        expect($lines)->toHaveCount(4);
        expect($lines[0])->toBe('period,buyer_id,sales_value');
        // The special-character cell is quoted per CSV rules.
        expect($lines[3])->toContain('"a,b""c"');
    } finally {
        @unlink($path);
    }
});

it('writes a readable XLSX workbook whose cells match the streamed rows (R29.4)', function () {
    $path = tempExportPath('xlsx');

    try {
        $written = (new SpreadsheetReportWriter(ExportFormat::EXCEL))->write(sampleExportRows(), $path, ['title' => 'Sales']);

        expect($written)->toBe(3);
        expect(file_exists($path))->toBeTrue();

        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();

        // Header row.
        expect($sheet->getCell('A1')->getValue())->toBe('period');
        expect($sheet->getCell('B1')->getValue())->toBe('buyer_id');
        expect($sheet->getCell('C1')->getValue())->toBe('sales_value');

        // First data row.
        expect($sheet->getCell('A2')->getValue())->toBe('2024-01-01');
        expect($sheet->getCell('B2')->getValue())->toBe('7');

        $book->disconnectWorksheets();
    } finally {
        @unlink($path);
    }
});

it('writes a valid printable PDF document for the rows (R29.4)', function () {
    $path = tempExportPath('pdf');

    try {
        $written = (new PrintablePdfReportWriter)->write(sampleExportRows(), $path, [
            'title' => 'Sales report',
            'date_from' => '2024-01-01T00:00:00+00:00',
            'date_to' => '2024-01-31T23:59:59+00:00',
        ]);

        expect($written)->toBe(3);
        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);
        // A genuine PDF: begins with the header and ends with the EOF marker,
        // and declares at least one page.
        expect(str_starts_with($contents, '%PDF-1.'))->toBeTrue();
        expect($contents)->toContain('/Type /Catalog');
        expect($contents)->toContain('/Type /Page');
        expect(str_contains($contents, '%%EOF'))->toBeTrue();
    } finally {
        @unlink($path);
    }
});

it('produces a valid header-only artifact for an empty result set', function () {
    $csv = tempExportPath('csv');
    $pdf = tempExportPath('pdf');

    try {
        expect((new CsvStreamReportWriter)->write([], $csv))->toBe(0);
        expect(file_exists($csv))->toBeTrue();

        expect((new PrintablePdfReportWriter)->write([], $pdf, ['title' => 'Empty']))->toBe(0);
        expect(str_starts_with(file_get_contents($pdf), '%PDF-1.'))->toBeTrue();
    } finally {
        @unlink($csv);
        @unlink($pdf);
    }
});

it('resolves the correct writer for each format via the factory', function () {
    $factory = new ReportExportWriterFactory;

    expect($factory->for(ExportFormat::EXCEL))->toBeInstanceOf(SpreadsheetReportWriter::class);
    expect($factory->for(ExportFormat::CSV))->toBeInstanceOf(CsvStreamReportWriter::class);
    expect($factory->for(ExportFormat::PDF))->toBeInstanceOf(PrintablePdfReportWriter::class);
});
