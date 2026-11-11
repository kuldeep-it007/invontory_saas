<?php

declare(strict_types=1);

namespace App\Services;

use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * The single, shared, role-aware report query layer (design "Reporting Engine",
 * R29, R31.3).
 *
 * This is the ONE query path both the standard report catalog (task 11.6) and
 * the AI layer (task 13) run through: the AI layer never executes SQL — it maps
 * a natural-language request to a {@see ReportQuerySpec} and hands it to THIS
 * service, exactly as a standard report does (R31.3, R31.4). Centralizing
 * execution here is what guarantees both paths obey the same scoping, defaults,
 * allow-listing, pagination, and robustness rules.
 *
 * `run()` performs, in order:
 *
 *   1. {@see applyDefaults()} — when the request omits a date range, default it
 *      to the previous calendar month (R29.2).
 *   2. {@see authorizeAndScope()} — tenant scoping is PHYSICAL (the query runs
 *      on the active tenant connection, so it can only ever see this tenant's
 *      rows); on top of that, role scoping confines a Distributor/Dealer
 *      principal to its own account data and denies a principal that may not
 *      read reports (R28.10).
 *   3. {@see validateAllowList()} — reject any grouping/sort column not on the
 *      report's allow-list before building SQL, so an arbitrary column (from a
 *      caller or the AI) can never reach the query (R31.2).
 *   4. {@see ReportCache::remember()} — serve the result from the per-(tenant,
 *      report, filter) cache under a short TTL, computing it on a miss. Keyed by
 *      the RESOLVED spec (after defaulting + scoping) and namespaced per tenant,
 *      so a repeated identical request reuses the aggregate and a relevant write
 *      invalidates it (R29.8).
 *   5. {@see executeIndexedAggregation()} — (the cache miss path) build the
 *      report's indexed, SQL-side aggregation on the active tenant connection,
 *      apply the validated grouping/sort, and return a single bounded, paginated
 *      page selecting only needed columns (R29.3, R38.1). The report's base
 *      query LEFT JOINs related tables and null-coalesces their columns so a
 *      missing or soft-deleted relation is silently omitted rather than dropping
 *      the row (R29.7).
 *
 * Report definitions are injected (keyed by {@see ReportType}), so the catalog
 * of concrete reports lives in its own small definitions (task 11.6) while this
 * engine owns every cross-cutting rule.
 */
class ReportQueryService
{
    /**
     * The Laravel connection name for the active tenant database. All report
     * queries run here, so tenant scoping is physical rather than a predicate
     * (R28.10).
     */
    public const CONNECTION = 'tenant';

    /**
     * @var array<string, ReportDefinition> keyed by {@see ReportType::value}
     */
    private array $definitions = [];

    /**
     * The per-tenant report aggregate cache (R29.8). Defaulted so callers that
     * construct the service directly (unit tests, the catalog) still get the
     * caching behaviour; the container injects the shared singleton.
     */
    private ReportCache $cache;

    /**
     * @param  iterable<ReportDefinition>  $definitions  The concrete report catalog (task 11.6).
     * @param  ReportCache|null  $cache  The report aggregate cache (R29.8); a default is created when omitted.
     */
    public function __construct(iterable $definitions = [], ?ReportCache $cache = null)
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }

        $this->cache = $cache ?? new ReportCache;
    }

    /**
     * Register (or replace) a report definition. Keyed by report type so each
     * type resolves to exactly one definition.
     */
    public function register(ReportDefinition $definition): void
    {
        $this->definitions[$definition->type()->value] = $definition;
    }

    /**
     * Whether a definition is registered for the given report type.
     */
    public function supports(ReportType $type): bool
    {
        return isset($this->definitions[$type->value]);
    }

    /**
     * The allow-list schema of every registered report, keyed by report-type
     * wire value: for each type, the columns it may be grouped by and sorted by.
     *
     * This is the single source of truth the AI layer (task 13.3) uses to (a)
     * describe the available reports to the model when mapping a natural-language
     * request to a {@see ReportQuerySpec} and (b) reject any AI-produced spec
     * whose type/grouping/sort falls outside the allow-list BEFORE execution
     * (R31.2). Exposing it here — rather than re-deriving it in the AI layer —
     * keeps the allow-list owned by the definitions the engine actually
     * enforces, so the two can never drift.
     *
     * @return array<string, array{groupable: list<string>, sortable: list<string>}>
     */
    public function reportSchema(): array
    {
        $schema = [];

        foreach ($this->definitions as $value => $definition) {
            $schema[$value] = [
                'groupable' => $definition->groupableColumns(),
                'sortable' => $definition->sortableColumns(),
            ];
        }

        return $schema;
    }

    /**
     * Execute a report spec under the given access context and return a single
     * bounded, paginated page (R29.3). Applies, in order, the previous-month
     * default (R29.2), tenant+role scoping (R28.10), allow-list validation of
     * grouping/sort (R31.2), then serves the indexed SQL-side aggregation
     * through the per-(tenant, report, filter) cache (R29.8, R29.3, R29.7,
     * R38.1).
     *
     * Caching sits AFTER defaulting and scoping so the cache key is derived from
     * the EFFECTIVE spec: a request that omits the date range and one that spells
     * out the previous month resolve to the same key, and a buyer principal's
     * force-scoped spec can never collide with a tenant-wide one. Allow-list
     * validation runs before the cache so an invalid column is always rejected
     * (R31.2) rather than hidden behind a cached entry. A read following a
     * relevant write misses the cache (its generation was bumped) and recomputes,
     * so no stale aggregate is ever served (R29.8, Property 31).
     *
     * @param  DateTimeInterface|null  $relativeTo  Reference date for the default date range (default: now).
     *
     * @throws AuthorizationException when the principal may not read the requested data (R28.10).
     * @throws InvalidArgumentException when no definition is registered for the type, or a grouping/sort column is not allow-listed (R31.2).
     */
    public function run(ReportQuerySpec $spec, ReportAccessContext $context, ?DateTimeInterface $relativeTo = null): ReportResult
    {
        $definition = $this->definitionFor($spec->type);

        $spec = $this->applyDefaults($spec, $relativeTo);
        $spec = $this->authorizeAndScope($spec, $context);
        $this->validateAllowList($spec, $definition);

        $resolvedSpec = $spec;

        return $this->cache->remember(
            $resolvedSpec,
            fn (): ReportResult => $this->executeIndexedAggregation($resolvedSpec, $definition),
        );
    }

    /**
     * Resolve a spec the same way {@see run()} does — previous-month default
     * (R29.2), tenant+role scoping (R28.10), and allow-list validation (R31.2)
     * — but WITHOUT pagination or caching, returning the resolved spec and its
     * fully built (grouped, sorted) query so a caller can stream EVERY matching
     * row.
     *
     * This is the entry point the export pipeline uses: a large export must
     * stream the whole result set as a queued job (R29.5), so it needs the same
     * scoped, validated, aggregated query the paginated reader runs — just
     * without the single-page window. Every cross-cutting rule still applies,
     * so an export can never bypass scoping or the allow-list. The short-TTL
     * aggregate cache (R29.8) is deliberately skipped here: an export walks the
     * whole set once in chunks, so caching a single page would not help it.
     *
     * @return array{0: ReportQuerySpec, 1: Builder} the resolved spec and its built query.
     *
     * @throws AuthorizationException when the principal may not read the requested data (R28.10).
     * @throws InvalidArgumentException when the type is unknown or a column is not allow-listed (R31.2).
     */
    public function resolveQuery(ReportQuerySpec $spec, ReportAccessContext $context, ?DateTimeInterface $relativeTo = null): array
    {
        $definition = $this->definitionFor($spec->type);

        $spec = $this->applyDefaults($spec, $relativeTo);
        $spec = $this->authorizeAndScope($spec, $context);
        $this->validateAllowList($spec, $definition);

        $query = $definition->baseQuery($spec->filters);

        if ($spec->grouping !== []) {
            $definition->applyGrouping($query, $spec->grouping);
        }

        foreach ($spec->sort as $directive) {
            $query->orderBy($definition->sortExpression($directive->column), $directive->isDescending() ? 'desc' : 'asc');
        }

        return [$spec, $query];
    }

    /**
     * Stream every row matching a spec as plain associative arrays, in chunks,
     * for export (R29.5). Reuses {@see resolveQuery()} so defaults, scoping, and
     * allow-listing are identical to a paginated read, then walks the result in
     * database-side chunks (ordered by the spec's sort, or by the first selected
     * column for a stable walk) so a very large report never materialises wholly
     * in memory — the export writer appends each chunk to the file as it
     * arrives.
     *
     * @param  int  $chunkSize  Rows pulled per database round-trip.
     * @return \Generator<int, array<string, mixed>>
     *
     * @throws AuthorizationException when the principal may not read the requested data (R28.10).
     * @throws InvalidArgumentException when the type is unknown or a column is not allow-listed (R31.2).
     */
    public function stream(ReportQuerySpec $spec, ReportAccessContext $context, int $chunkSize = 1000, ?DateTimeInterface $relativeTo = null): \Generator
    {
        [, $query] = $this->resolveQuery($spec, $context, $relativeTo);

        $page = 1;

        do {
            $rows = (clone $query)->forPage($page, $chunkSize)->get();

            foreach ($rows as $row) {
                yield (array) $row;
            }

            $page++;
        } while ($rows->count() === $chunkSize);
    }

    /**
     * Default the date range to the previous calendar month when the request
     * omitted it, leaving an explicit range untouched (R29.2).
     */
    private function applyDefaults(ReportQuerySpec $spec, ?DateTimeInterface $relativeTo): ReportQuerySpec
    {
        return $spec->withFilters($spec->filters->withDefaultDateRange($relativeTo));
    }

    /**
     * Enforce role scoping on top of the physical tenant isolation (R28.10).
     *
     * - A principal that cannot read reports is denied outright.
     * - A company-side principal (Platform_Admin / Company_Admin / Company_Staff
     *   / Read-only) may read tenant-wide data: the query is confined only by
     *   the active tenant database, so the spec passes through unchanged.
     * - A buyer-side principal (Distributor / Dealer) is confined to its OWN
     *   account: its spec is force-scoped to its (buyerType, buyerId) binding,
     *   overriding any buyer filter the request supplied so it can never read
     *   another buyer's data. A buyer principal with no resolvable binding has
     *   no readable scope and is denied.
     *
     * @throws AuthorizationException when the principal has no readable report scope.
     */
    private function authorizeAndScope(ReportQuerySpec $spec, ReportAccessContext $context): ReportQuerySpec
    {
        if (! $context->canViewReports) {
            throw new AuthorizationException('You are not permitted to view reports.');
        }

        if ($context->hasTenantWideScope()) {
            return $spec;
        }

        if ($context->isBuyerScoped()) {
            if (! $context->hasBuyerBinding()) {
                throw new AuthorizationException('Your account has no report scope.');
            }

            return $spec->withFilters(
                $spec->filters->scopedToBuyer((string) $context->buyerType, (int) $context->buyerId)
            );
        }

        // A principal with the reports.view ability but no recognized scope
        // (neither tenant-wide nor a bound buyer) is denied rather than silently
        // reading tenant-wide data.
        throw new AuthorizationException('Your role has no report scope.');
    }

    /**
     * Reject any grouping or sort column that is not on the report's allow-list
     * BEFORE building the query, so an arbitrary column — whether from a direct
     * caller or an AI-produced spec — can never reach SQL (R31.2).
     *
     * @throws InvalidArgumentException naming the offending column.
     */
    private function validateAllowList(ReportQuerySpec $spec, ReportDefinition $definition): void
    {
        $groupable = $definition->groupableColumns();
        foreach ($spec->grouping as $column) {
            if (! in_array($column, $groupable, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Grouping column [%s] is not allowed for report [%s]. Allowed: %s.',
                    $column,
                    $spec->type->value,
                    $groupable === [] ? '(none)' : implode(', ', $groupable),
                ));
            }
        }

        $sortable = $definition->sortableColumns();
        foreach ($spec->sort as $directive) {
            if (! in_array($directive->column, $sortable, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Sort column [%s] is not allowed for report [%s]. Allowed: %s.',
                    $directive->column,
                    $spec->type->value,
                    $sortable === [] ? '(none)' : implode(', ', $sortable),
                ));
            }
        }
    }

    /**
     * Build and run the report's indexed, SQL-side aggregation on the active
     * tenant connection, applying the validated grouping and sort, and return a
     * single bounded page of rows (selecting only needed columns — R29.3,
     * R38.1).
     *
     * The base query already LEFT JOINs related tables with null-coalesced
     * output, so a missing/soft-deleted relation is omitted without error
     * (R29.7). One extra COUNT over the same grouped shape yields the total so
     * the caller gets complete pagination metadata without loading every row.
     *
     * Protected (not private) so a test double can replace the DB-touching
     * aggregation while exercising the real defaulting/scoping/allow-list/cache
     * pipeline around it.
     */
    protected function executeIndexedAggregation(ReportQuerySpec $spec, ReportDefinition $definition): ReportResult
    {
        // The definition builds its base query on the active tenant connection
        // (physical scoping), so the aggregation can only ever touch this
        // tenant's rows (R28.10).
        $query = $definition->baseQuery($spec->filters);

        if ($spec->grouping !== []) {
            $definition->applyGrouping($query, $spec->grouping);
        }

        foreach ($spec->sort as $directive) {
            $query->orderBy($definition->sortExpression($directive->column), $directive->isDescending() ? 'desc' : 'asc');
        }

        $total = $this->countTotal($query);

        $rows = $query
            ->forPage($spec->page, $spec->limit)
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->values()
            ->all();

        return new ReportResult(
            rows: $rows,
            spec: $spec,
            page: $spec->page,
            perPage: $spec->limit,
            total: $total,
        );
    }

    /**
     * Count total matching rows for pagination metadata. A grouped/aggregated
     * query is counted by its distinct group rows (count over the derived
     * table) so the total reflects the number of report rows, not the number of
     * underlying records.
     */
    private function countTotal(Builder $query): int
    {
        $clone = clone $query;

        if (! empty($clone->groups)) {
            return $this->countGrouped($clone);
        }

        return (int) $clone->count();
    }

    /**
     * Count the rows produced by a grouped query by wrapping it in a derived
     * table and counting the groups — the only correct way to total a GROUP BY
     * result.
     */
    private function countGrouped(Builder $grouped): int
    {
        $connection = $grouped->getConnection();

        $sql = $grouped->toSql();
        $bindings = $grouped->getBindings();

        $row = $connection->selectOne(
            'select count(*) as aggregate from ('.$sql.') as report_group_count',
            $bindings,
        );

        return (int) ($row->aggregate ?? 0);
    }

    /**
     * Resolve the registered definition for a report type.
     *
     * @throws InvalidArgumentException when the type has no registered definition.
     */
    private function definitionFor(ReportType $type): ReportDefinition
    {
        if (! isset($this->definitions[$type->value])) {
            throw new InvalidArgumentException(sprintf(
                'No report definition is registered for type [%s].',
                $type->value,
            ));
        }

        return $this->definitions[$type->value];
    }

    /**
     * The active tenant database connection every report query runs on.
     */
    private function connection(): string
    {
        return self::CONNECTION;
    }
}
