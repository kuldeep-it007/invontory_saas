<?php

declare(strict_types=1);

use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Services\ReportCache;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Regression: a cached report must survive a SERIALIZING cache store
|--------------------------------------------------------------------------
|
| Production caches reports in Redis, which serializes values. Laravel's
| hardened default (`cache.serializable_classes => false`) refuses to rebuild
| PHP objects on read, so caching the ReportResult object itself came back as
| __PHP_Incomplete_Class and every REPEATED report request failed with HTTP 500.
|
| The rest of the suite uses the `array` store, which never serializes, so it
| could not catch this. These tests use the `file` store, which serializes
| through the same `serializable_classes` rule as Redis.
*/

beforeEach(function () {
    config([
        'cache.default' => 'file',
        'cache.serializable_classes' => false,
        'cache.stores.file.path' => storage_path('framework/cache/report-serialization-test'),
    ]);
    Cache::store('file')->flush();
});

afterEach(function () {
    Cache::store('file')->flush();
});

function serializationTestSpec(): ReportQuerySpec
{
    return ReportQuerySpec::fromArray([
        'type' => 'sales',
        'filters' => ['from' => '2026-11-01', 'to' => '2026-11-30'],
        'limit' => 25,
        'page' => 1,
    ]);
}

it('serves a cache hit from a serializing store without crashing', function () {
    $cache = new ReportCache;
    $spec = serializationTestSpec();
    $rows = [
        ['period' => '2026-11-12', 'buyer' => 'Northern Distributors', 'sales_value' => '6600.00'],
        ['period' => '2026-11-12', 'buyer' => 'Western Supplies Co', 'sales_value' => '19025.00'],
    ];
    $computed = 0;
    $compute = function () use ($spec, $rows, &$computed): ReportResult {
        $computed++;

        return new ReportResult(rows: $rows, spec: $spec, page: 1, perPage: 25, total: 2);
    };

    $first = $cache->remember($spec, $compute);   // miss: computes + stores
    $second = $cache->remember($spec, $compute);  // hit: read back from the file store

    expect($computed)->toBe(1)
        ->and($second)->toBeInstanceOf(ReportResult::class)
        ->and($second->rows)->toBe($rows)
        ->and($second->total)->toBe(2)
        ->and($second->page)->toBe(1)
        ->and($second->perPage)->toBe(25)
        ->and($second->spec)->toBe($spec)
        ->and($second->toArray())->toEqual($first->toArray());
});

it('recomputes instead of failing when an old entry holds a serialized object', function () {
    $cache = new ReportCache;
    $spec = serializationTestSpec();

    // Simulate an entry written by the previous build: the whole ReportResult
    // object. Under serializable_classes=false it reads back as an incomplete
    // class, which used to be returned straight to the caller.
    $remember = new ReflectionMethod(ReportCache::class, 'cacheKey');
    $remember->setAccessible(true);
    $key = $remember->invoke($cache, $spec);
    Cache::put($key, new ReportResult(rows: [['stale' => true]], spec: $spec, page: 1, perPage: 25, total: 1), 60);

    $result = $cache->remember($spec, fn (): ReportResult => new ReportResult(
        rows: [['fresh' => true]], spec: $spec, page: 1, perPage: 25, total: 1,
    ));

    expect($result)->toBeInstanceOf(ReportResult::class)
        ->and($result->rows)->toBe([['fresh' => true]]);
});
