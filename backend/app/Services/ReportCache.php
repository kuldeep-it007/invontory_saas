<?php

declare(strict_types=1);

namespace App\Services;

use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The per-tenant cache for expensive report aggregates (R29.8, design
 * "Reporting Engine").
 *
 * Report aggregates are costly to compute (grouped SQL-side sums over the
 * ledger/order tables), yet the same (tenant, report, filter) combination is
 * requested repeatedly between writes — a dashboard reloading, pagination over
 * the same report, the AI layer re-running a spec. This service caches each
 * computed {@see ReportResult} under a key derived from the ACTIVE TENANT, the
 * report type, and the fully-resolved filter/grouping/sort/pagination spec,
 * with a SHORT time-to-live, and invalidates every cached report for a tenant
 * when a relevant write occurs (stock movement, order confirmation /
 * cancellation / return, payment).
 *
 * ## Keying — per (tenant, report, filter) with physical isolation
 *
 * The cache key folds together three things (mirroring the design pseudocode
 * `cacheKey = hash(currentTenant, spec)`):
 *
 *   1. the ACTIVE TENANT id, so one tenant can never read another tenant's
 *      cached aggregate (cache isolation — the same way counts are namespaced
 *      in {@see QuotaService});
 *   2. a per-tenant GENERATION token (see below), so invalidation is O(1); and
 *   3. a canonical fingerprint of the RESOLVED spec — report type plus every
 *      filter, grouping column, sort directive, and the page/limit — so two
 *      different filter combinations get two different cache entries and a read
 *      only ever reuses a result computed for the exact same request.
 *
 * The spec passed here is the one AFTER the service applied the previous-month
 * default (R29.2) and role scoping (R28.10), so the fingerprint reflects the
 * EFFECTIVE query, not the raw request — a request that omits the date range
 * and one that spells out last month resolve to the same key, as they must.
 *
 * ## Invalidation — bump a generation token, not a key sweep
 *
 * "Invalidate every cached report for this tenant on a relevant write" (R29.8)
 * must be O(1) and must work on every cache driver the app uses (Redis in
 * production, the array store in tests), so it CANNOT rely on key enumeration
 * or cache tags. Instead each tenant has a monotonically increasing GENERATION
 * counter that is woven into every report cache key. {@see invalidate()} simply
 * increments that counter: all previously-written keys (which embed the old
 * generation) become unreachable at once and expire on their own TTL, while the
 * next read computes fresh under the new generation. This is the same
 * generation/namespace-versioning technique used for bulk cache invalidation
 * and keeps a write cheap regardless of how many reports were cached.
 */
class ReportCache
{
    /**
     * Short TTL for a cached report aggregate (R29.8). Kept small so that even
     * absent an explicit invalidation a cached aggregate can never serve stale
     * data for long; the generation bump (see {@see invalidate()}) is the
     * authoritative, immediate invalidation on relevant writes.
     */
    public const TTL_SECONDS = 60;

    /**
     * Return the cached {@see ReportResult} for the resolved spec, computing and
     * storing it on a miss (R29.8).
     *
     * The result is cached under a key derived from the active tenant, the
     * tenant's current generation token, and a canonical fingerprint of the
     * resolved spec, for {@see TTL_SECONDS}. A read following an invalidating
     * write (which bumped the generation) necessarily misses the old key and
     * recomputes, so it can never observe a stale aggregate (R29.8, Property
     * 31).
     *
     * @param  Closure(): ReportResult  $compute  Produces the fresh result on a cache miss.
     */
    public function remember(ReportQuerySpec $spec, Closure $compute): ReportResult
    {
        if (! $this->cacheAvailable()) {
            // No cache backend resolvable (e.g. a plain unit test with no booted
            // application): degrade gracefully by computing directly. The result
            // is identical to a cache miss — correctness never depends on the
            // cache, only performance (R29.8).
            return $compute();
        }

        // Cache PLAIN data only (rows + total), never the ReportResult object.
        // Laravel's hardened cache refuses to unserialize objects
        // (`cache.serializable_classes => false`, a gadget-chain defence), so a
        // cached object came back as __PHP_Incomplete_Class and every repeated
        // report request failed with a 500. Rebuilding the result from arrays
        // keeps that protection on. The test suite's array store never
        // serializes, which is why this only showed up on Redis.
        $cached = Cache::remember(
            $this->cacheKey($spec),
            self::TTL_SECONDS,
            static function () use ($compute): array {
                $result = $compute();

                return [
                    'rows' => $result->rows,
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'total' => $result->total,
                ];
            },
        );

        // An entry written by an older build (a serialized object) is unusable;
        // drop it and compute fresh rather than failing the request.
        if (! is_array($cached) || ! array_key_exists('rows', $cached)) {
            Cache::forget($this->cacheKey($spec));

            return $compute();
        }

        return new ReportResult(
            rows: $cached['rows'],
            spec: $spec,
            page: (int) $cached['page'],
            perPage: (int) $cached['per_page'],
            total: $cached['total'] === null ? null : (int) $cached['total'],
        );
    }

    /**
     * Invalidate every cached report aggregate for the ACTIVE TENANT (R29.8).
     *
     * Called after any write that could change a report's result — a stock
     * movement, an order confirmation / cancellation / return, a recorded
     * payment. Rather than sweeping keys, it bumps the tenant's generation
     * token so every key written under the previous generation becomes
     * unreachable in one O(1) operation; the next read recomputes fresh.
     */
    public function invalidate(): void
    {
        if (! $this->cacheAvailable()) {
            // No cache backend resolvable: nothing is cached, so there is
            // nothing to invalidate (R29.8 is a no-op without a cache).
            return;
        }

        $key = $this->generationKey();

        // Prefer an atomic increment where the store supports it; fall back to
        // a read-modify-write so invalidation still works on the array store
        // used in tests.
        try {
            $next = Cache::increment($key);
            if ($next === false || $next === null) {
                throw new \RuntimeException('increment unsupported');
            }
        } catch (\Throwable) {
            $current = (int) Cache::get($key, 0);
            Cache::forever($key, $current + 1);
        }
    }

    /**
     * The current generation token for the active tenant, defaulting to 0 when
     * no invalidation has occurred yet. Woven into every report cache key so a
     * bump invalidates them all at once.
     */
    public function generation(): int
    {
        if (! $this->cacheAvailable()) {
            return 0;
        }

        return (int) Cache::get($this->generationKey(), 0);
    }

    /**
     * Whether a cache backend is resolvable in the current runtime. False in a
     * plain unit test with no booted application (no facade root), in which case
     * the service degrades to direct computation — correctness is unaffected,
     * only the performance optimization is skipped.
     */
    private function cacheAvailable(): bool
    {
        try {
            return Cache::getFacadeRoot() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build the per-(tenant, generation, resolved-spec) cache key under which a
     * report result is stored. A short, fixed-length hash of the canonical spec
     * fingerprint keeps the key bounded while remaining unique per distinct
     * (report, filter) combination.
     */
    private function cacheKey(ReportQuerySpec $spec): string
    {
        return sprintf(
            'report:%s:gen%d:%s',
            $this->tenantToken(),
            $this->generation(),
            hash('sha256', $this->fingerprint($spec)),
        );
    }

    /**
     * The per-tenant generation counter's cache key. Namespaced by tenant so
     * one tenant's invalidation never touches another's cached reports.
     */
    private function generationKey(): string
    {
        return sprintf('report:%s:generation', $this->tenantToken());
    }

    /**
     * A stable, canonical string that uniquely identifies the RESOLVED spec:
     * report type + every filter + grouping + sort + pagination. Built in a
     * deterministic order (and JSON-encoded) so logically identical specs
     * always produce the same fingerprint and distinct specs never collide.
     */
    private function fingerprint(ReportQuerySpec $spec): string
    {
        $filters = $spec->filters;

        $sort = [];
        foreach ($spec->sort as $directive) {
            $sort[] = $directive->column.':'.$directive->direction;
        }

        return (string) json_encode([
            'type' => $spec->type->value,
            'from' => $filters->from?->toIso8601String(),
            'to' => $filters->to?->toIso8601String(),
            'buyer_type' => $filters->buyerType,
            'buyer_id' => $filters->buyerId,
            'seller_type' => $filters->sellerType,
            'seller_id' => $filters->sellerId,
            'product_id' => $filters->productId,
            'category_id' => $filters->categoryId,
            'stock_point_id' => $filters->stockPointId,
            'status' => $filters->status,
            'grouping' => $spec->grouping,
            'sort' => $sort,
            'limit' => $spec->limit,
            'page' => $spec->page,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * A token identifying the active tenant for cache namespacing. Resolves the
     * tenant from the active tenancy context; falls back to `none` outside a
     * resolved tenant (e.g. central routes / some unit tests) so keys are still
     * well-formed. Mirrors {@see QuotaService::countCacheKey()}.
     */
    private function tenantToken(): string
    {
        if (! function_exists('tenant')) {
            return 'none';
        }

        /** @var int|string|null $id */
        $id = tenant('id');

        return (string) ($id ?? 'none');
    }
}
