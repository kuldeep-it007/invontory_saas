<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * The paginated outcome of running a {@see ReportQuerySpec} through the
 * {@see ReportQueryService}.
 *
 * A report always returns a bounded PAGE of rows plus the pagination metadata a
 * caller needs to request the next page and the resolved spec that produced the
 * page (so a caller can see the effective date range after the previous-month
 * default and any role scoping were applied — R29.2, R29.3, R28.10).
 *
 * Rows are plain associative arrays of the SELECTed columns only (select only
 * needed columns — R38.1); aggregates are computed SQL-side so a row already
 * holds its summed/grouped values rather than a hydrated relation graph.
 */
final class ReportResult
{
    /**
     * @param  list<array<string, mixed>>  $rows  The page's rows (selected columns only).
     * @param  ReportQuerySpec  $spec  The resolved spec (defaults + scoping applied).
     * @param  int  $page  The 1-based page number returned.
     * @param  int  $perPage  The page size.
     * @param  int|null  $total  Total matching rows across all pages, when counted.
     */
    public function __construct(
        public readonly array $rows,
        public readonly ReportQuerySpec $spec,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?int $total = null,
    ) {}

    /**
     * The number of rows on this page.
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * Whether another page MAY exist. When a total is known, this compares the
     * window end against it; otherwise it infers "more" from a full page.
     */
    public function hasMorePages(): bool
    {
        if ($this->total !== null) {
            return $this->page * $this->perPage < $this->total;
        }

        return $this->count() === $this->perPage;
    }

    /**
     * Render the result as the standard list envelope the API returns.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->rows,
            'meta' => [
                'type' => $this->spec->type->value,
                'page' => $this->page,
                'per_page' => $this->perPage,
                'count' => $this->count(),
                'total' => $this->total,
                'has_more' => $this->hasMorePages(),
                'date_from' => $this->spec->filters->from?->toIso8601String(),
                'date_to' => $this->spec->filters->to?->toIso8601String(),
            ],
        ];
    }
}
