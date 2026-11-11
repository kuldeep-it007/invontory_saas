<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use App\Services\ReportCache;
use App\Services\ReportQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Task 11.8 — Report caching and invalidation (R29.8)
|--------------------------------------------------------------------------
|
| Verifies the per-(tenant, report, filter) report aggregate cache and its
| invalidation hooks WITHOUT a database, using the array cache store (the
| testing default) and a counting fake report definition:
|   • an identical repeated request reuses the cached aggregate (the expensive
|     computation runs once) — R29.8;
|   • a DIFFERENT filter combination gets its own cache entry (keys are per
|     report + filter) — R29.8;
|   • invalidating the cache (what a relevant write does) makes the next read
|     recompute, so no stale aggregate is ever served — R29.8, Property 31.
|
| The computation closure counts how many times it actually ran, which is the
| direct observable for "cached vs recomputed".
|
*/

beforeEach(function () {
    Cache::store('array')->flush();
});

/**
 * A fake report definition whose baseQuery is never used here — the service's
 * execution path is replaced by a counting closure via a test subclass below —
 * but it satisfies registration/allow-list validation for the chosen type.
 */
function cacheFakeDefinition(): ReportDefinition
{
    return new class implements ReportDefinition
    {
        public function type(): ReportType
        {
            return ReportType::SALES;
        }

        public function groupableColumns(): array
        {
            return ['product'];
        }

        public function sortableColumns(): array
        {
            return ['total'];
        }

        public function baseQuery(ReportFilters $filters): Builder
        {
            throw new RuntimeException('baseQuery() must not run; execution is stubbed by the counter.');
        }

        public function applyGrouping(Builder $query, array $grouping): void {}

        public function sortExpression(string $column): string
        {
            return $column;
        }
    };
}

/**
 * A ReportQueryService whose aggregation step is replaced by a counter, so a
 * test can observe exactly how many times the expensive computation ran versus
 * how many times a result was served from cache. Everything else (defaulting,
 * scoping, allow-list validation, caching) runs for real.
 */
function countingReportService(ReportCache $cache): object
{
    return new class([cacheFakeDefinition()], $cache) extends ReportQueryService
    {
        public int $computations = 0;

        protected function executeIndexedAggregation(ReportQuerySpec $spec, ReportDefinition $definition): ReportResult
        {
            $this->computations++;

            return new ReportResult(
                rows: [['computation' => $this->computations]],
                spec: $spec,
                page: $spec->page,
                perPage: $spec->limit,
                total: 1,
            );
        }
    };
}

function cacheReportContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

it('computes once and serves repeated identical requests from cache (R29.8)', function () {
    $cache = new ReportCache;
    $service = countingReportService($cache);

    $spec = ReportQuerySpec::fromArray([
        'type' => 'sales',
        'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
    ]);

    $first = $service->run($spec, cacheReportContext());
    $second = $service->run($spec, cacheReportContext());

    // The expensive aggregation ran exactly once; the second read was a hit.
    expect($service->computations)->toBe(1);
    expect($second->rows)->toEqual($first->rows);
});

it('keys the cache per filter combination so different filters compute separately (R29.8)', function () {
    $cache = new ReportCache;
    $service = countingReportService($cache);

    $january = ReportQuerySpec::fromArray([
        'type' => 'sales',
        'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
    ]);
    $february = ReportQuerySpec::fromArray([
        'type' => 'sales',
        'filters' => ['from' => '2024-02-01', 'to' => '2024-02-29'],
    ]);

    $service->run($january, cacheReportContext());
    $service->run($february, cacheReportContext());
    // Re-reading each uses its own cache entry.
    $service->run($january, cacheReportContext());
    $service->run($february, cacheReportContext());

    expect($service->computations)->toBe(2);
});

it('recomputes after an invalidating write so no stale aggregate is served (R29.8, Property 31)', function () {
    $cache = new ReportCache;
    $service = countingReportService($cache);

    $spec = ReportQuerySpec::fromArray([
        'type' => 'sales',
        'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
    ]);

    $service->run($spec, cacheReportContext());   // miss -> compute (1)
    $service->run($spec, cacheReportContext());   // hit (still 1)
    expect($service->computations)->toBe(1);

    // A relevant write bumps the generation (what StockMovementService /
    // OrderConfirmationService / PaymentService do post-commit).
    $cache->invalidate();

    $afterWrite = $service->run($spec, cacheReportContext()); // miss -> recompute (2)

    expect($service->computations)->toBe(2);
    // The recomputed read reflects the fresh computation, not the stale cached one.
    expect($afterWrite->rows)->toEqual([['computation' => 2]]);
});

it('bumps a monotonic per-tenant generation token on each invalidation (R29.8)', function () {
    $cache = new ReportCache;

    expect($cache->generation())->toBe(0);

    $cache->invalidate();
    expect($cache->generation())->toBe(1);

    $cache->invalidate();
    expect($cache->generation())->toBe(2);
});

it('does not treat allow-list violations as cacheable (R31.2 before R29.8)', function () {
    $cache = new ReportCache;
    $service = countingReportService($cache);

    $spec = ReportQuerySpec::fromArray([
        'type' => 'sales',
        'grouping' => ['not_allowed_column'],
    ]);

    // Allow-list validation runs BEFORE the cache, so an invalid column is
    // always rejected rather than hidden behind (or poisoning) a cache entry.
    expect(fn () => $service->run($spec, cacheReportContext()))
        ->toThrow(InvalidArgumentException::class);

    expect($service->computations)->toBe(0);
});
