<?php

declare(strict_types=1);

use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use App\Services\ReportCache;
use App\Services\ReportQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 31: Report cache consistency
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 31: Report cache consistency
|
| Validates: Requirements 29.8
|
| Design reference: "Reporting Engine" / ReportCache + ReportQueryService.
|   - R29.8  THE Platform SHALL cache expensive report aggregates per (tenant,
|            report, filter) under a short TTL and invalidate them on any write
|            that could change a report's result, so a read following such a
|            write returns a freshly computed aggregate — never a stale one.
|
| The invariant (design.md §Property 31):
|   A read following an invalidating write equals a FRESH computation (no stale
|   aggregates). Concretely, across ANY interleaving of
|     • reads of various filter combinations, and
|     • invalidating writes (what StockMovementService / OrderConfirmationService
|       / OrderCancellationService / OrderReturnService / PaymentService do on
|       every relevant write),
|   the result a caller observes for a spec ALWAYS reflects the data as of the
|   LAST invalidation — the cache is a performance layer, never a source of
|   truth. Two further facets of the keying rule fall out of the same contract:
|     • an identical repeated request (no invalidation between) is served from
|       cache — the expensive aggregation runs AT MOST once per generation; and
|     • two DIFFERENT filter combinations key separately — one never serves the
|       other's cached aggregate.
|
| Strategy — pure logic, no database.
|   ReportCache's remember()/invalidate() operate entirely on the configured
|   cache store (the `array` store in the test env — phpunit.xml CACHE_STORE),
|   so this proof needs NO MySQL. We drive the REAL ReportQueryService pipeline
|   (defaulting + scoping + allow-list + cache) via a counting subclass whose
|   aggregation step is replaced by a closure that (a) counts how many times it
|   actually ran and (b) stamps the result with a monotonically-increasing
|   "data version" that advances on every invalidating write — the stand-in for
|   the underlying ledger/order data mutating. If the cache ever served a stale
|   aggregate, the stamped version on the observed result would lag the current
|   data version, and the assertion would fail.
|
|   BlackBox (NOT hand-rolled) generates 100+ randomized programs. Each program
|   is a sequence of operations drawn over a small fixed pool of distinct filter
|   specs plus an "invalidate" operation; we run every program against a
|   reference model that tracks, per spec fingerprint, whether a cached entry is
|   live for the current generation, and assert the real service matches the
|   model on every step (served value == current data version, and the compute
|   count equals the model's expected miss count).
*/

beforeEach(function () {
    // Each generated program starts from a clean cache so generations and
    // cached entries do not leak across iterations. The array store is the
    // test-env default (phpunit.xml), so no database is involved.
    Cache::store('array')->flush();
});

/**
 * A fake report definition: it only has to satisfy registration and allow-list
 * validation for the chosen type — its baseQuery is never reached because the
 * counting service below stubs the aggregation step.
 */
function reportCacheConsistencyDefinition(): ReportDefinition
{
    return new class implements ReportDefinition
    {
        public function type(): ReportType
        {
            return ReportType::SALES;
        }

        public function groupableColumns(): array
        {
            return ['product', 'category', 'period'];
        }

        public function sortableColumns(): array
        {
            return ['total'];
        }

        public function baseQuery(ReportFilters $filters): Builder
        {
            throw new RuntimeException('baseQuery() must not run; aggregation is stubbed by the counter.');
        }

        public function applyGrouping(Builder $query, array $grouping): void {}

        public function sortExpression(string $column): string
        {
            return $column;
        }
    };
}

/**
 * A ReportQueryService whose expensive aggregation is replaced by a counter
 * that also stamps each computed result with the CURRENT data version — the
 * observable proxy for "freshly computed as of the latest write". Everything
 * else (defaulting, scoping, allow-list, the real ReportCache) runs for real.
 *
 * `$dataVersion` is advanced by the test whenever it performs an invalidating
 * write, modelling the underlying data changing. A correctly-invalidated read
 * recomputes and therefore stamps the NEW version; a stale cache hit would
 * carry an OLD version and be caught.
 */
function reportCacheConsistencyService(ReportCache $cache): object
{
    return new class([reportCacheConsistencyDefinition()], $cache) extends ReportQueryService
    {
        public int $computations = 0;

        public int $dataVersion = 0;

        protected function executeIndexedAggregation(ReportQuerySpec $spec, ReportDefinition $definition): ReportResult
        {
            $this->computations++;

            return new ReportResult(
                rows: [['data_version' => $this->dataVersion]],
                spec: $spec,
                page: $spec->page,
                perPage: $spec->limit,
                total: 1,
            );
        }
    };
}

/**
 * The pool of DISTINCT resolved specs the generated programs read. Each differs
 * from the others in exactly one filter/grouping/sort/pagination facet, so a
 * correct cache MUST key them separately. Keyed by a short label the generator
 * selects from.
 *
 * @return array<string, ReportQuerySpec>
 */
function reportCacheConsistencySpecPool(): array
{
    return [
        'jan' => ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
        ]),
        'feb' => ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['from' => '2024-02-01', 'to' => '2024-02-29'],
        ]),
        'jan-by-product' => ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
            'grouping' => ['product'],
        ]),
        'jan-product-filter' => ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31', 'productId' => 7],
        ]),
        'jan-page2' => ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
            'page' => 2,
        ]),
    ];
}

/** The access context used by every read: a company-wide reader. */
function reportCacheConsistencyContext(): App\Reports\ReportAccessContext
{
    return new App\Reports\ReportAccessContext(
        roles: [App\Auth\Roles::COMPANY_ADMIN],
        canViewReports: true,
    );
}

it('serves a freshly computed aggregate after any invalidating write and never a stale one, across any read/write interleaving (Property 31, R29.8)', function () {
    $specLabels = array_keys(reportCacheConsistencySpecPool());

    // Each operation is EITHER a read of one of the pool's specs (its label) OR
    // an invalidating write (the sentinel '@invalidate'). BlackBox generates
    // 100+ randomized programs of between 1 and 20 operations.
    $operation = Set::of('@invalidate', ...$specLabels);

    static::forAll(
        Set::sequence($operation)->between(1, 20),
    )->then(function (array $program): void {
        Cache::store('array')->flush();

        $specPool = reportCacheConsistencySpecPool();
        $context = reportCacheConsistencyContext();

        $cache = new ReportCache;
        $service = reportCacheConsistencyService($cache);

        // Reference model. `liveEntries` holds the spec labels whose cached
        // aggregate is live for the CURRENT generation; an invalidating write
        // bumps the generation (so every previously-cached label is dropped) and
        // advances the data version. `expectedComputations` tracks how many
        // times the aggregation SHOULD have run (one per miss).
        $liveEntries = [];
        $expectedComputations = 0;

        foreach ($program as $op) {
            if ($op === '@invalidate') {
                // Model a relevant write: the data changes, then the cache for
                // this tenant is invalidated (bumping the generation).
                $service->dataVersion++;
                $cache->invalidate();
                $liveEntries = [];

                continue;
            }

            // A read of a specific resolved spec.
            $wasLive = array_key_exists($op, $liveEntries);
            if (! $wasLive) {
                $expectedComputations++;
                $liveEntries[$op] = true;
            }

            $result = $service->run($specPool[$op], $context);

            // (A) Freshness / no-stale: the observed aggregate ALWAYS reflects
            // the data as of the latest invalidation (the current version),
            // never an older cached version (R29.8, Property 31).
            expect($result->rows)->toBe(
                [['data_version' => $service->dataVersion]],
                sprintf('Read of [%s] must reflect the current data version %d, not a stale cached one.', $op, $service->dataVersion),
            );

            // (B) Keying: an identical repeated request within the same
            // generation is a cache HIT (no recompute), while a distinct filter
            // combination or a post-invalidation read is a MISS. The real
            // compute count must match the model exactly, which simultaneously
            // proves identical requests are cached and different filters key
            // separately (R29.8).
            expect($service->computations)->toBe(
                $expectedComputations,
                sprintf('Compute count after reading [%s] must equal the model miss count.', $op),
            );
        }
    });
});
