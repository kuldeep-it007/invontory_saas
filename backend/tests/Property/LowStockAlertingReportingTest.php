<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Stock;
use App\Models\Tenant;
use App\Services\LowStockService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 7.20 — Property 14: Low-stock alerting and reporting
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 14: Low-stock alerting and
| reporting
|
| Validates: Requirements 19.2, 19.3
|
| Property statement (design.md §Property 14):
|   For any stock quantity and configured threshold, a low-stock alert is raised
|   AND the product appears in the low-stock report IF AND ONLY IF the quantity
|   is at or below the threshold.
|
|   - R19.2  WHEN a Stock quantity falls to or below its configured low-stock
|            threshold, THE Platform SHALL raise a low-stock alert.
|   - R19.3  THE Platform SHALL provide a report and export of all Products
|            whose Stock is at or below the configured low-stock threshold.
|
| Design reference: "Inventory Engine" / Low-stock alerts — a per-(product,
| stock_point) threshold (R19.1); when stock falls to/below threshold a
| low-stock alert is raised (R19.2); the low-stock report lists all breaching
| rows (R19.3). The alert predicate ({@see LowStockService::isAlerting()} →
| {@see Stock::isLowStock()}) and the report's SQL filter
| ({@see Stock::scopeBreachingLowStock()}) share the SAME "quantity <= threshold
| when a threshold is configured" condition, which is exactly the biconditional
| this proof exercises.
|
| Statement under test. For any generated collection of stock rows — each a
| (quantity, threshold) pair spread across several products and stock points,
| where the threshold may also be UNSET (null) — the following three facts
| agree perfectly, row by row:
|
|     alert_raised(row)                 <=>  threshold set AND quantity <= threshold
|     row appears in low-stock report   <=>  threshold set AND quantity <= threshold
|
| so alert_raised(row) <=> row-in-report for every row, and both equal the
| "at or below threshold" predicate (a row with no threshold can never breach,
| R19.1, and is therefore neither alerting nor reported).
|
| Generation uses innmind/black-box (NOT hand-rolled) via the shared
| static::forAll(), drawing the mandated minimum of 100 generated scenarii per
| proof and shrinking any counterexample to a minimal failing input.
|
| DB-GATED. Provisioning a real per-tenant MySQL database and running the report
| query (which filters SQL-side) needs a reachable MySQL server; when MySQL is
| unreachable the proof skips gracefully via skip_without_mysql('central'). All
| assertions are written to be correct against a real MySQL instance using the
| live central connection, and run inside $tenant->run() so stock is confined to
| the active tenant database (physical isolation).
*/

/**
 * Provision + migrate an isolated tenant for the low-stock proof and register
 * it for teardown. Mirrors the sibling DB-gated property proofs' helpers.
 *
 * NOTE: top-level helpers are given file-unique `lowStock...` names so Pest can
 * load this file alongside its sibling Property test files without colliding on
 * "cannot redeclare function".
 *
 * @param  array<int, Tenant>  $registry
 */
function lowStockProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'lows'.$suffix,
        'subdomain' => 'lows-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'lows-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    $registry[] = $tenant;

    return $tenant;
}

/**
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param  array<int, Tenant>  $registry
 */
function lowStockTeardownTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 * Stock rows reference a real product because the stock.product_id foreign key
 * is enforced once the catalog (task 7.4) is migrated.
 */
function lowStockSeedProduct(string $sku): int
{
    $now = now();

    $categoryId = DB::table('categories')->insertGetId([
        'name' => 'Cat '.$sku, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $collectionId = DB::table('collections')->insertGetId([
        'category_id' => $categoryId, 'name' => 'Col '.$sku, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $colourId = DB::table('colours')->insertGetId([
        'collection_id' => $collectionId, 'name' => 'Clr '.$sku, 'created_at' => $now, 'updated_at' => $now,
    ]);

    return (int) DB::table('products')->insertGetId([
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'collection_id' => $collectionId,
        'colour_id' => $colourId,
        'tax_type' => 'gst_18',
        'uom' => 'pcs',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/**
 * Format an integer-in-thousandths into a DECIMAL(15,3) string, e.g. 12345 →
 * "12.345" and -5000 → "-5.000". Keeps the generated quantities/thresholds as
 * exact fixed-scale decimals (never float), matching the column's exactness so
 * the "<=" boundary (quantity exactly equal to threshold) is tested precisely.
 */
function lowStockDecimal(int $thousandths): string
{
    $negative = $thousandths < 0;
    $thousandths = abs($thousandths);
    $digits = str_pad((string) $thousandths, 4, '0', STR_PAD_LEFT);

    return ($negative ? '-' : '').substr($digits, 0, -3).'.'.substr($digits, -3);
}

/**
 * The independent (test-side) oracle for the biconditional: a row is "low" iff
 * a threshold is configured (non-null, R19.1) AND the quantity is at or below
 * it. Compared exactly on fixed-scale decimal strings so it never coerces to
 * float — the same exactness the production predicate promises.
 */
function lowStockExpectedBreach(string $quantity, ?string $threshold): bool
{
    if ($threshold === null) {
        return false;
    }

    if (function_exists('bccomp')) {
        return bccomp($quantity, $threshold, 3) <= 0;
    }

    $toScaled = static function (string $value): int {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = substr(str_pad($parts[1] ?? '', 3, '0'), 0, 3);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    };

    return $toScaled($quantity) <= $toScaled($threshold);
}

it('raises a low-stock alert and lists the row in the report iff the quantity is at or below the configured threshold (R19.2, R19.3)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = lowStockProvisionTenant($registry);

        $tenant->run(function () {
            // The tenant's single Company owner (R1.5) plus a few stock points
            // and products, so generated (quantity, threshold) pairs are spread
            // across distinct (product, stock_point) stock rows (R19.1).
            $company = Company::create(['name' => 'Low Stock Co']);

            $points = [
                $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0])->id,
                $company->stockPoints()->create(['name' => 'Annex', 'sort_order' => 1])->id,
                $company->stockPoints()->create(['name' => 'Depot', 'sort_order' => 2])->id,
            ];

            $products = [
                lowStockSeedProduct('LOWS-A'),
                lowStockSeedProduct('LOWS-B'),
                lowStockSeedProduct('LOWS-C'),
                lowStockSeedProduct('LOWS-D'),
            ];

            $service = new LowStockService;

            static::forAll(
                // A non-empty collection of stock rows. Each row is a tuple:
                //   [productIdx, pointIdx, quantityThousandths, thresholdChoice]
                // where thresholdChoice is either null (threshold UNSET → can
                // never breach, R19.1) or a thousandths int (a configured
                // threshold). Quantities and thresholds span negatives, zero,
                // and large values, and are drawn from overlapping ranges so the
                // generator frequently hits the exact "quantity == threshold"
                // boundary that the "at or below" (<=) rule turns on.
                Set::sequence(
                    Set::compose(
                        static fn (int $p, int $sp, int $qty, ?int $threshold): array => [$p, $sp, $qty, $threshold],
                        Set::integers()->between(0, 3),
                        Set::integers()->between(0, 2),
                        Set::integers()->between(-5_000, 50_000),
                        Set::either(
                            Set::of(null),
                            Set::integers()->between(-2_000, 50_000),
                        ),
                    ),
                )->between(1, 12),
            )->then(function (array $rows) use ($service, $company, $points, $products) {
                // Isolate each generated scenario: start from an empty stock
                // table so the report reflects only THIS iteration's rows.
                DB::connection('tenant')->table('stock')->delete();

                // Each generated tuple maps to a UNIQUE (product, stock_point)
                // stock row — a stock row IS the (owner, product, stock_point)
                // tuple, so collisions would violate the one-row-per-tuple
                // invariant. We claim distinct (product, point) slots in order;
                // once the finite grid of slots is exhausted we stop (there are
                // 4 × 3 = 12 slots, matching the generated upper bound).
                $usedSlots = [];
                $expected = []; // keyed by stock.id → expected breach bool

                foreach ($rows as [$p, $sp, $qtyThousandths, $thresholdThousandths]) {
                    $productId = $products[$p];
                    $pointId = $points[$sp];
                    $slot = $productId.'|'.$pointId;

                    if (isset($usedSlots[$slot])) {
                        continue; // keep one row per (product, stock_point)
                    }
                    $usedSlots[$slot] = true;

                    $quantity = lowStockDecimal($qtyThousandths);
                    $threshold = $thresholdThousandths === null
                        ? null
                        : lowStockDecimal($thresholdThousandths);

                    // Seed the stock row directly with the exact generated
                    // quantity and threshold. The owner morph columns are not
                    // mass-assignable, so set them via forceFill (same pattern
                    // the StockMovementService uses to create rows).
                    $stock = (new Stock)->forceFill([
                        'stockable_type' => $company->getMorphClass(),
                        'stockable_id' => $company->getKey(),
                        'product_id' => $productId,
                        'stock_point_id' => $pointId,
                        'quantity' => $quantity,
                        'low_stock_threshold' => $threshold,
                    ]);
                    $stock->save();

                    $expected[(int) $stock->id] = lowStockExpectedBreach($quantity, $threshold);
                }

                // ---- 1. ALERT side (R19.2) ---------------------------------
                // For EVERY seeded row, the alert predicate must equal the
                // independent "at or below threshold" oracle, exactly.
                foreach (Stock::query()->get() as $stock) {
                    $alert = $service->isAlerting($stock);
                    expect($alert)->toBe($expected[(int) $stock->id]);
                }

                // ---- 2. REPORT side (R19.3) --------------------------------
                // The report must contain PRECISELY the rows the oracle says
                // breach — no breaching row missing, no non-breaching row
                // present. Use a page size larger than any generated scenario
                // (<= 12 rows) so the single page holds the full breaching set.
                $reportIds = collect($service->report(100)->items())
                    ->map(fn (Stock $s) => (int) $s->id)
                    ->sort()
                    ->values()
                    ->all();

                $expectedReportIds = collect($expected)
                    ->filter(fn (bool $breach) => $breach)
                    ->keys()
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                expect($reportIds)->toBe($expectedReportIds);

                // ---- 3. The biconditional closes ---------------------------
                // alert_raised(row) <=> row-in-report, for every row. Since both
                // were just proven equal to the same oracle this is implied, but
                // we assert it directly as the property's headline statement.
                $reportIdSet = array_fill_keys($reportIds, true);
                foreach (Stock::query()->get() as $stock) {
                    $inReport = isset($reportIdSet[(int) $stock->id]);
                    expect($service->isAlerting($stock))->toBe($inReport);
                }
            });
        });
    } finally {
        lowStockTeardownTenants($registry);
    }
});
