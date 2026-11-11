<?php

declare(strict_types=1);

namespace App\Reports;

use InvalidArgumentException;

/**
 * The structured, validated description of a single report query (design
 * "Reporting Engine"):
 *
 *     ReportQuerySpec {
 *       type: ReportType
 *       filters: ReportFilters          // date range, buyer/seller, product/category, stock point, status (R29.1)
 *       grouping: string[]              // ALLOW-LISTED columns only
 *       sort: { column, dir }[]         // ALLOW-LISTED columns only
 *       limit: int
 *       page: int
 *     }
 *
 * This is the ONE shape both the standard report catalog and the AI layer pass
 * to the {@see ReportQueryService}. The AI layer never emits SQL — it emits a
 * spec, which is validated against each report's allow-list before execution
 * (R31.1, R31.2, R31.4). The grouping and sort columns carried here are checked
 * against the per-report allow-list at execution time by the report definition,
 * so an unknown or non-groupable column can never leak into a query.
 *
 * `limit` and `page` are clamped to safe bounds on construction so a request
 * can never ask for an unbounded or non-positive page (every list query is
 * paginated — R29.3, R38.1). The spec is immutable; defaulting (the previous
 * calendar month) and scoping (role confinement) produce NEW specs via
 * {@see withFilters()} rather than mutating this one.
 */
final class ReportQuerySpec
{
    /**
     * The largest page size a single report page may request. Keeps a report
     * query bounded so it stays indexed and resource-light (R29.3, R38.1).
     */
    public const MAX_LIMIT = 500;

    /**
     * The default page size when a request omits `limit`.
     */
    public const DEFAULT_LIMIT = 50;

    /**
     * @param  list<string>  $grouping  Allow-listed grouping columns (validated per-report at execution).
     * @param  list<ReportSort>  $sort  Allow-listed sort directives (validated per-report at execution).
     */
    public function __construct(
        public readonly ReportType $type,
        public readonly ReportFilters $filters,
        public readonly array $grouping = [],
        public readonly array $sort = [],
        public readonly int $limit = self::DEFAULT_LIMIT,
        public readonly int $page = 1,
    ) {}

    /**
     * Build a spec from a loosely-typed array (a decoded request body or an
     * AI-produced structure). The `type` MUST be an allow-listed
     * {@see ReportType} value; an unknown type is rejected here before anything
     * else, so an invalid report type never reaches the query layer (R31.2,
     * R31.5). Grouping/sort columns are carried verbatim and validated against
     * the per-report allow-list at execution time.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws InvalidArgumentException when `type` is missing or not allow-listed.
     */
    public static function fromArray(array $input): self
    {
        $typeValue = isset($input['type']) ? (string) $input['type'] : '';
        $type = ReportType::tryFromValue($typeValue);

        if ($type === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown report type [%s]. Allowed types: %s.',
                $typeValue,
                implode(', ', ReportType::values()),
            ));
        }

        $filters = ReportFilters::fromArray(
            is_array($input['filters'] ?? null) ? $input['filters'] : []
        );

        $grouping = [];
        foreach ((array) ($input['grouping'] ?? []) as $column) {
            if (is_string($column) && $column !== '') {
                $grouping[] = $column;
            }
        }

        $sort = [];
        foreach ((array) ($input['sort'] ?? []) as $directive) {
            if (is_array($directive) && isset($directive['column'])) {
                $sort[] = new ReportSort(
                    (string) $directive['column'],
                    (string) ($directive['dir'] ?? $directive['direction'] ?? 'asc'),
                );
            }
        }

        return new self(
            type: $type,
            filters: $filters,
            grouping: $grouping,
            sort: $sort,
            limit: self::clampLimit($input['limit'] ?? null),
            page: self::clampPage($input['page'] ?? null),
        );
    }

    /**
     * Return a copy of this spec with a different filter set, preserving type,
     * grouping, sort, and pagination. Used to apply the previous-calendar-month
     * default (R29.2) and role scoping (R28.10) without mutating the original.
     */
    public function withFilters(ReportFilters $filters): self
    {
        return new self(
            type: $this->type,
            filters: $filters,
            grouping: $this->grouping,
            sort: $this->sort,
            limit: $this->limit,
            page: $this->page,
        );
    }

    /**
     * The zero-based row offset for the current page, derived from the clamped
     * `page` and `limit`. Pages are 1-based, so page 1 has offset 0.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * Clamp a requested limit into [1, MAX_LIMIT], defaulting an absent/invalid
     * value to {@see DEFAULT_LIMIT}. Guarantees every page is bounded (R29.3).
     */
    private static function clampLimit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_LIMIT;
        }

        $limit = (int) $value;

        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * Clamp a requested page to a positive integer, defaulting an
     * absent/invalid value to the first page.
     */
    private static function clampPage(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 1;
        }

        $page = (int) $value;

        return $page < 1 ? 1 : $page;
    }
}
