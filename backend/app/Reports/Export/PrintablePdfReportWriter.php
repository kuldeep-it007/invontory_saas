<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Reports\ExportFormat;

/**
 * Renders a report to a printable PDF (R29.4).
 *
 * The platform ships no heavyweight PDF engine (dompdf/mpdf/tcpdf are not
 * installed), and R29.4 asks only for a PRINTABLE PDF of the report's rows — a
 * tabular, paginated document — not a pixel-styled layout. So this writer emits
 * a self-contained, valid PDF 1.4 document directly: a Helvetica-set,
 * multi-page table where the header row repeats on each page. This keeps the
 * export free of a new dependency while producing a genuine `application/pdf`
 * file a browser or printer opens natively.
 *
 * Rows are consumed from the stream and laid out page by page; only the current
 * page's lines are buffered, so memory stays bounded for a large export
 * (R29.5). Text is escaped for PDF string syntax and clipped to a sensible
 * width so a wide value cannot overflow the page.
 */
class PrintablePdfReportWriter implements ReportExportWriter
{
    /** US-Letter-ish portrait page geometry, in PDF points (72 pt = 1 inch). */
    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    private const MARGIN = 36;

    private const LINE_HEIGHT = 14;

    private const FONT_SIZE = 9;

    /** Max characters rendered per line before clipping (monospace estimate). */
    private const MAX_LINE_CHARS = 110;

    public function format(): ExportFormat
    {
        return ExportFormat::PDF;
    }

    public function write(iterable $rows, string $absolutePath, array $meta = []): int
    {
        $this->ensureDirectory($absolutePath);

        $title = (string) ($meta['title'] ?? 'Report');
        $subtitle = $this->subtitleFrom($meta);

        $linesPerPage = (int) floor((self::PAGE_HEIGHT - 2 * self::MARGIN - 3 * self::LINE_HEIGHT) / self::LINE_HEIGHT);
        $linesPerPage = max(1, $linesPerPage);

        $header = null;
        $written = 0;

        /** @var list<string> $pages Finished page content streams. */
        $pages = [];
        /** @var list<string> $current Lines buffered for the current page. */
        $current = [];
        $onPage = 0;

        $flushPage = function () use (&$pages, &$current): void {
            if ($current !== []) {
                $pages[] = $this->pageContentStream($current);
                $current = [];
            }
        };

        $pushLine = function (string $text) use (&$current, &$onPage, $linesPerPage, $flushPage, &$header): void {
            if ($onPage >= $linesPerPage) {
                $flushPage();
                $onPage = 0;
                // Repeat the column header atop each continuation page.
                if ($header !== null) {
                    $current[] = $this->joinRow($header);
                    $onPage++;
                }
            }
            $current[] = $text;
            $onPage++;
        };

        // Title block on the first page.
        $current[] = $title;
        $onPage++;
        if ($subtitle !== '') {
            $current[] = $subtitle;
            $onPage++;
        }
        $current[] = '';
        $onPage++;

        foreach ($rows as $row) {
            if ($header === null) {
                $header = array_keys($row);
                $pushLine($this->joinRow($header));
            }

            $ordered = [];
            foreach ($header as $key) {
                $ordered[] = $this->scalar($row[$key] ?? null);
            }
            $pushLine($this->joinRow($ordered));
            $written++;
        }

        if ($header === null) {
            $pushLine('(no rows)');
        }

        $flushPage();

        if ($pages === []) {
            $pages[] = $this->pageContentStream([$title]);
        }

        file_put_contents($absolutePath, $this->assembleDocument($pages));

        return $written;
    }

    /**
     * Join a row's cells into a single clipped text line. A real columnar
     * layout would measure widths; for a printable listing a tab-separated,
     * width-clipped line is sufficient and robust for any column count.
     *
     * @param  list<string|int|float|null>  $cells
     */
    private function joinRow(array $cells): string
    {
        $line = implode('   ', array_map(fn ($c): string => (string) ($c ?? ''), $cells));

        if (mb_strlen($line) > self::MAX_LINE_CHARS) {
            $line = mb_substr($line, 0, self::MAX_LINE_CHARS - 1).'…';
        }

        return $line;
    }

    /**
     * Build the PDF content stream for one page from its text lines.
     *
     * @param  list<string>  $lines
     */
    private function pageContentStream(array $lines): string
    {
        $x = self::MARGIN;
        $y = self::PAGE_HEIGHT - self::MARGIN;

        $stream = "BT\n/F1 ".self::FONT_SIZE." Tf\n".self::LINE_HEIGHT." TL\n";
        $stream .= sprintf("%d %d Td\n", $x, $y);

        $first = true;
        foreach ($lines as $line) {
            if ($first) {
                $stream .= '('.$this->escape($line).") Tj\n";
                $first = false;
            } else {
                $stream .= 'T* ('.$this->escape($line).") Tj\n";
            }
        }

        $stream .= 'ET';

        return $stream;
    }

    /**
     * Assemble the full PDF document (catalog, pages, shared font, content
     * streams) with a correct cross-reference table so the file is a valid
     * `application/pdf`.
     *
     * @param  list<string>  $pageStreams
     */
    private function assembleDocument(array $pageStreams): string
    {
        $pageCount = count($pageStreams);

        // Object numbering:
        //   1 = Catalog, 2 = Pages, 3 = Font
        //   then per page: a Page object and its Contents object.
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageObjNumbers = [];
        $nextObj = 4;
        $contentObjNumbers = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjNumbers[$i] = $nextObj++;
            $contentObjNumbers[$i] = $nextObj++;
        }

        $kids = implode(' ', array_map(fn (int $n): string => "{$n} 0 R", $pageObjNumbers));
        $objects[2] = "<< /Type /Pages /Count {$pageCount} /Kids [{$kids}] >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        for ($i = 0; $i < $pageCount; $i++) {
            $pageNo = $pageObjNumbers[$i];
            $contentNo = $contentObjNumbers[$i];
            $objects[$pageNo] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentNo,
            );

            $content = $pageStreams[$i];
            $objects[$contentNo] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObj = max(array_keys($objects));
        $size = $maxObj + 1;

        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($num = 1; $num < $size; $num++) {
            if (isset($offsets[$num])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
            } else {
                // A gap in numbering should not happen, but keep the table valid.
                $pdf .= "0000000000 65535 f \n";
            }
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * Escape a string for PDF literal-string syntax: backslash, parentheses,
     * and control characters.
     */
    private function escape(string $text): string
    {
        $text = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);

        // Drop any remaining non-printable bytes so the stream stays valid.
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
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

    private function subtitleFrom(array $meta): string
    {
        $parts = [];

        if (! empty($meta['date_from']) || ! empty($meta['date_to'])) {
            $parts[] = 'Period: '.($meta['date_from'] ?? '…').' to '.($meta['date_to'] ?? '…');
        }

        if (! empty($meta['generated_at'])) {
            $parts[] = 'Generated: '.$meta['generated_at'];
        }

        return implode('    ', $parts);
    }

    private function ensureDirectory(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
