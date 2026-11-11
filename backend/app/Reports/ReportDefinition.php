<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Query\Builder;

/**
 * The contract one report type implements so the {@see ReportQueryService} can
 * execute it uniformly (design "Reporting Engine": the service is the single
 * query path, each report plugs into it).
 *
 * A definition declares three things and nothing more:
 *
 *   1. its {@see ReportType};
 *   2. the ALLOW-LISTED columns it may be grouped by and sorted by — the service
 *      checks a spec's grouping/sort against these before building the query, so
 *      an unknown or non-groupable column is rejected and never reaches SQL
 *      (R31.2); and
 *   3. how to build its BASE query from the resolved filters: an indexed,
 *      SQL-side aggregation over the active tenant connection that SELECTs only
 *      the needed columns and LEFT JOINs related tables with null-coalesced
 *      output so a missing or soft-deleted relation is silently omitted rather
 *      than dropping the row or erroring (R29.3, R29.7, R38.1).
 *
 * The service owns the cross-cutting concerns (default date range, role
 * scoping, applying the validated grouping/sort, pagination), so a definition
 * stays small and focused on its own shape — mirroring the thin-service
 * convention used across the codebase.
 */
interface ReportDefinition
{
    /**
     * The report type this definition handles.
     */
    public function type(): ReportType;

    /**
     * The columns this report may be GROUPED BY (allow-list). An empty list
     * means the report exposes no caller-chosen grouping. Column keys are the
     * stable names a caller/AI uses in a spec, mapped to real SQL expressions
     * inside {@see baseQuery()} / {@see applyGrouping()}.
     *
     * @return list<string>
     */
    public function groupableColumns(): array;

    /**
     * The columns this report may be SORTED BY (allow-list). Only these may
     * appear in a spec's `sort` directives; anything else is rejected before
     * execution (R31.2).
     *
     * @return list<string>
     */
    public function sortableColumns(): array;

    /**
     * Build the base query for this report against the active tenant connection
     * from the already-resolved filters (date range defaulted, buyer scope
     * applied). The query SELECTs only needed columns, aggregates SQL-side, and
     * LEFT JOINs related tables with null-coalesced output so missing/soft-
     * deleted relations are omitted without error (R29.3, R29.7, R38.1).
     *
     * Grouping, sort, and pagination are layered on by the service afterwards
     * using the validated grouping/sort, so this method does not apply them.
     */
    public function baseQuery(ReportFilters $filters): Builder;

    /**
     * Apply a validated grouping to the base query. The columns passed here have
     * already been checked against {@see groupableColumns()}, so the definition
     * only maps each allow-listed key to its SQL grouping expression.
     *
     * @param  list<string>  $grouping
     */
    public function applyGrouping(Builder $query, array $grouping): void;

    /**
     * Map a validated allow-listed sort column key to the real SQL column/
     * expression to ORDER BY. The key has already been checked against
     * {@see sortableColumns()}.
     */
    public function sortExpression(string $column): string;
}
