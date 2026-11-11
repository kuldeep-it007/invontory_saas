<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Tenant;
use App\Reports\Definitions\OutstandingAgeingReportDefinition;
use App\Reports\Definitions\PaymentsReportDefinition;
use App\Reports\Definitions\SalesReportDefinition;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Services\ReportCache;
use App\Services\ReportQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 11.7 — Property 29: Report aggregation correctness
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 29: Report aggregation
| correctness
|
| Validates: Requirements 28.3, 28.5, 28.6
|
| Property statement (tasks.md §11.7, design.md §Property 29):
|   Sales totals, payment/collection totals, and ageing bucket sums equal a
|   straightforward reference implementation over the same data.
|
|   - R28.3  Sales report — sold value/quantity over confirmed orders, grouped
|            by period/distributor/dealer/product/category.
|   - R28.5  Outstanding-and-ageing report — each buyer's unpaid invoice balance
|            split into current / 1-30 / 31-60 / 61-90 / 90-plus buckets.
|   - R28.6  Payments/collections report — amounts collected over the period.
|
| What the proof asserts, for every generated dataset:
|
|   1. SALES (R28.3). The SQL-side SUM of each row's `sales_value`
|      (= Σ qty·(unit_price − discount) over CONFIRMED-and-beyond order lines,
|      cancelled/draft/placed orders excluded) and the SUM of `quantity_sold`
|      equal an independent PHP reference computed from the same seeded lines.
|
|   2. PAYMENTS / COLLECTIONS (R28.6). The SQL-side SUM of `collected` and the
|      SUM of `payment_count` across all rows equal a PHP reference summed over
|      the payments whose `paid_at` falls inside the window.
|
|   3. OUTSTANDING AGEING (R28.5). For every buyer row the five bucket sums
|      (current / 1-30 / 31-60 / 61-90 / 90-plus) and `total_outstanding` equal
|      a PHP reference that ages each invoice's outstanding
|      (= max(amount − allocated, 0)) by DATEDIFF(as-of, invoice date) into
|      exactly one bucket, AND the five buckets reconcile to the total
|      (bucket_current + 1-30 + 31-60 + 61-90 + 90-plus == total_outstanding).
|
| In every case the report's aggregate is compared to a reference implementation
| that walks the SAME seeded rows in plain PHP — a different computation path
| reaching the same number, which is exactly what "aggregation correctness"
| means. Monetary comparisons are made on integer cents to avoid binary float
| noise; the datasets are generated so sums stay exact at the 2-dp currency and
| 3-dp quantity precision the schema stores.
|
| Generation uses innmind/black-box (NOT hand-rolled) via the shared
| static::forAll(), drawing the mandated minimum of 100 generated scenarii per
| proof and shrinking any counterexample to a minimal failing input.
|
| DB-GATED. Provisioning a real per-tenant MySQL database and running the
| reports through live SQL (SUM / conditional-SUM / DATEDIFF) needs a reachable
| MySQL server; when MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'), mirroring the sibling DB-gated suites. All work
| runs inside $tenant->run() so data is confined to the active tenant database
| (physical isolation, R28.10).
*/

/**
 * Provision + migrate an isolated tenant for the aggregation proof and register
 * it for teardown. File-unique `aggCorr...` helper names avoid colliding with
 * the sibling Property files Pest loads alongside this one.
 *
 * @param  array<int, Tenant>  $registry
 */
function aggCorrProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'aggc'.$suffix,
        'subdomain' => 'aggc-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'aggc-'.$suffix.'.example.test']);

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
function aggCorrTeardownTenants(array $registry): void
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
 * Seed one catalog product (with its category/collection/colour) inside the
 * active tenant database and return [productId, categoryId].
 *
 * @return array{0: int, 1: int}
 */
function aggCorrSeedProduct(string $sku): array
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

    $productId = (int) DB::table('products')->insertGetId([
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'collection_id' => $collectionId,
        'colour_id' => $colourId,
        'tax_type' => 'gst_18',
        'uom' => 'pcs',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [$productId, (int) $categoryId];
}

/**
 * A company-side principal that may read the whole tenant's data, so the
 * service applies NO buyer scoping and the proof observes exactly the seeded
 * rows (R28.10).
 */
function aggCorrAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

it('aggregates sales value and quantity SQL-side equal to an independent reference over the same confirmed order lines (R28.3, Property 29)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = aggCorrProvisionTenant($registry);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Agg Sales Co']);

            // A small product pool so generated lines spread across products
            // (and so the catalog LEFT JOINs resolve). Three buyers so sales
            // span distinct buyer dimensions.
            $products = [
                aggCorrSeedProduct('AGG-SA')[0],
                aggCorrSeedProduct('AGG-SB')[0],
                aggCorrSeedProduct('AGG-SC')[0],
            ];
            $buyerIds = [7, 8, 9];

            $sellerType = $company->getMorphClass();
            $sellerId = (int) $company->getKey();

            // Only confirmed-and-beyond orders are sales; draft/placed/cancelled
            // are excluded by the report, so the generator draws from BOTH to
            // prove the exclusion holds against the reference.
            $saleStatuses = ['confirmed', 'dispatched', 'delivered', 'closed'];
            $nonSaleStatuses = ['draft', 'placed', 'cancelled'];

            // Anchor sale dates on a fixed day so an explicit window (computed
            // below) deterministically covers every seeded order regardless of
            // when the test runs.
            $anchor = CarbonImmutable::parse('2024-05-10 09:00:00');

            $cache = new ReportCache;
            $service = new ReportQueryService([new SalesReportDefinition], $cache);

            static::forAll(
                // A non-empty collection of order lines. Each tuple is:
                //   [buyerIdx, productIdx, statusIsSale(bool), statusPick,
                //    qtyThousandths, unitPriceCents, discountCents]
                // qty is a positive multiple of 1000 thousandths (whole units)
                // so qty·(price−discount) stays exact in integer cents; price
                // and discount are non-negative cents with discount<=price so
                // the per-line value is never negative (matches R12.5/R39.1).
                Set::sequence(
                    Set::compose(
                        static fn (int $b, int $p, bool $isSale, int $statusPick, int $qtyUnits, int $priceCents, int $discPct): array
                            => [$b, $p, $isSale, $statusPick, $qtyUnits, $priceCents, $discPct],
                        Set::integers()->between(0, 2),
                        Set::integers()->between(0, 2),
                        Set::either(Set::of(true), Set::of(false)),
                        Set::integers()->between(0, 3),
                        Set::integers()->between(1, 50),       // whole units 1..50
                        Set::integers()->between(0, 500000),   // unit price 0..5000.00
                        Set::integers()->between(0, 100),      // discount as % of price
                    ),
                )->between(1, 20),
            )->then(function (array $lines) use (
                $service, $cache, $sellerType, $sellerId, $products, $buyerIds,
                $saleStatuses, $nonSaleStatuses, $anchor
            ) {
                $connection = DB::connection('tenant');

                // Isolate this scenario.
                $connection->table('order_lines')->delete();
                $connection->table('orders')->delete();
                $cache->invalidate();

                $now = now();

                // Independent reference accumulators over the SAME seeded data.
                $refValueCents = 0;   // Σ qty·(price − discount) over sale lines
                $refQtyUnits = 0;     // Σ qty over sale lines

                $daySeq = 0;
                foreach ($lines as [$b, $p, $isSale, $statusPick, $qtyUnits, $priceCents, $discPct]) {
                    $status = $isSale
                        ? $saleStatuses[$statusPick % count($saleStatuses)]
                        : $nonSaleStatuses[$statusPick % count($nonSaleStatuses)];

                    // Spread sale dates across a few days inside the window.
                    $createdAt = $anchor->addDays($daySeq % 10);
                    $daySeq++;

                    $orderId = (int) $connection->table('orders')->insertGetId([
                        'buyer_type' => 'App\\Models\\Distributor',
                        'buyer_id' => $buyerIds[$b],
                        'seller_type' => $sellerType,
                        'seller_id' => $sellerId,
                        'status' => $status,
                        'order_type' => 'standard',
                        'total' => 0,
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $now,
                    ]);

                    // Per-line discount in cents, clamped to <= price so the
                    // charged amount is never negative.
                    $discountCents = (int) floor($priceCents * $discPct / 100);
                    $discountCents = min($discountCents, $priceCents);

                    $unitPrice = number_format($priceCents / 100, 2, '.', '');
                    $discount = number_format($discountCents / 100, 2, '.', '');
                    $quantity = number_format($qtyUnits, 3, '.', ''); // whole units, 3dp

                    $connection->table('order_lines')->insert([
                        'order_id' => $orderId,
                        'product_id' => $products[$p],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($isSale) {
                        $refValueCents += $qtyUnits * ($priceCents - $discountCents);
                        $refQtyUnits += $qtyUnits;
                    }
                }

                // Explicit window covering every seeded order (the report
                // filters orders by created_at). Spans the whole anchor range.
                $from = $anchor->subDay()->startOfDay();
                $to = $anchor->addDays(11)->endOfDay();

                $result = $service->run(
                    ReportQuerySpec::fromArray([
                        'type' => 'sales',
                        'filters' => [
                            'from' => $from->format('Y-m-d H:i:s'),
                            'to' => $to->format('Y-m-d H:i:s'),
                        ],
                        'limit' => 500,
                    ]),
                    aggCorrAdminContext(),
                );

                // SQL-side totals rolled up across every returned row.
                $sqlValueCents = 0;
                $sqlQtyUnits = 0;
                foreach ($result->rows as $row) {
                    $sqlValueCents += (int) round(((float) $row['sales_value']) * 100);
                    $sqlQtyUnits += (int) round((float) $row['quantity_sold']);
                }

                // Aggregate correctness: SQL == independent reference (R28.3).
                expect($sqlValueCents)->toBe($refValueCents);
                expect($sqlQtyUnits)->toBe($refQtyUnits);
            });
        });
    } finally {
        aggCorrTeardownTenants($registry);
    }
});

it('aggregates collected payments SQL-side equal to an independent reference over the payments in the window (R28.6, Property 29)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = aggCorrProvisionTenant($registry);

        $tenant->run(function () {
            $buyerIds = [7, 8, 9];
            $methods = ['cash', 'upi', 'cheque', 'bank_transfer'];

            // Payments are dated by `paid_at` (a DATE). Anchor on a fixed day so
            // the explicit window deterministically covers them.
            $anchor = CarbonImmutable::parse('2024-04-01');

            $cache = new ReportCache;
            $service = new ReportQueryService([new PaymentsReportDefinition], $cache);

            static::forAll(
                // Each tuple: [buyerIdx, methodIdx, dayOffset, amountCents].
                // dayOffset ∈ [0, 20] keeps all payments inside the window.
                Set::sequence(
                    Set::compose(
                        static fn (int $b, int $m, int $day, int $amtCents): array => [$b, $m, $day, $amtCents],
                        Set::integers()->between(0, 2),
                        Set::integers()->between(0, 3),
                        Set::integers()->between(0, 20),
                        Set::integers()->between(1, 1000000), // 0.01 .. 10000.00
                    ),
                )->between(1, 25),
            )->then(function (array $payments) use ($service, $cache, $buyerIds, $methods, $anchor) {
                $connection = DB::connection('tenant');

                $connection->table('payment_allocations')->delete();
                $connection->table('payments')->delete();
                $cache->invalidate();

                $now = now();

                $refCollectedCents = 0;
                $refCount = 0;

                foreach ($payments as [$b, $m, $day, $amtCents]) {
                    $paidAt = $anchor->addDays($day);
                    $amount = number_format($amtCents / 100, 2, '.', '');

                    $connection->table('payments')->insert([
                        'buyer_type' => 'App\\Models\\Distributor',
                        'buyer_id' => $buyerIds[$b],
                        'amount' => $amount,
                        'method' => $methods[$m],
                        'reference' => null,
                        'paid_at' => $paidAt->toDateString(),
                        'recorded_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $refCollectedCents += $amtCents;
                    $refCount++;
                }

                // Explicit window covering every seeded payment date.
                $from = $anchor->subDay()->toDateString();
                $to = $anchor->addDays(21)->toDateString();

                $result = $service->run(
                    ReportQuerySpec::fromArray([
                        'type' => 'payments',
                        'filters' => ['from' => $from, 'to' => $to],
                        'limit' => 500,
                    ]),
                    aggCorrAdminContext(),
                );

                $sqlCollectedCents = 0;
                $sqlCount = 0;
                foreach ($result->rows as $row) {
                    $sqlCollectedCents += (int) round(((float) $row['collected']) * 100);
                    $sqlCount += (int) $row['payment_count'];
                }

                // Aggregate correctness: SQL == independent reference (R28.6).
                expect($sqlCollectedCents)->toBe($refCollectedCents);
                expect($sqlCount)->toBe($refCount);
            });
        });
    } finally {
        aggCorrTeardownTenants($registry);
    }
});

it('buckets outstanding invoices by age SQL-side equal to an independent reference, and the buckets reconcile to the total (R28.5, Property 29)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = aggCorrProvisionTenant($registry);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Agg Ageing Co']);
            $sellerType = $company->getMorphClass();
            $sellerId = (int) $company->getKey();

            $buyerIds = [7, 8, 9];

            // The as-of date the report ages against is the window end `to`.
            // Pin it so DATEDIFF is deterministic, and age invoices by whole
            // days back from it.
            $asOf = CarbonImmutable::parse('2024-06-30 00:00:00');

            $cache = new ReportCache;
            $service = new ReportQueryService([new OutstandingAgeingReportDefinition], $cache);

            static::forAll(
                // A non-empty collection of invoices. Each tuple is:
                //   [buyerIdx, ageDays, amountCents, allocPct]
                // ageDays ∈ [0, 150] spans every bucket (current / 1-30 / 31-60
                // / 61-90 / 90-plus); allocPct is how much of the invoice is
                // paid (0..120 %, >100 clamps to fully paid → zero outstanding,
                // exercising the GREATEST(.,0) floor).
                Set::sequence(
                    Set::compose(
                        static fn (int $b, int $age, int $amtCents, int $allocPct): array => [$b, $age, $amtCents, $allocPct],
                        Set::integers()->between(0, 2),
                        Set::integers()->between(0, 150),
                        Set::integers()->between(1, 500000), // 0.01 .. 5000.00
                        Set::integers()->between(0, 120),
                    ),
                )->between(1, 20),
            )->then(function (array $invoices) use (
                $service, $cache, $sellerType, $sellerId, $buyerIds, $asOf
            ) {
                $connection = DB::connection('tenant');

                $connection->table('payment_allocations')->delete();
                $connection->table('payments')->delete();
                $connection->table('invoices')->delete();
                $connection->table('orders')->delete();
                $cache->invalidate();

                $now = now();

                // Reference buckets per buyer id. Each entry:
                //   ['current','1_30','31_60','61_90','90_plus','total'] in cents
                $ref = [];
                $bucketKeyFor = static function (int $age): string {
                    if ($age <= 0) {
                        return 'current';
                    }
                    if ($age <= 30) {
                        return '1_30';
                    }
                    if ($age <= 60) {
                        return '31_60';
                    }
                    if ($age <= 90) {
                        return '61_90';
                    }

                    return '90_plus';
                };

                $numberSeq = 1;
                foreach ($invoices as [$b, $ageDays, $amtCents, $allocPct]) {
                    $buyerId = $buyerIds[$b];

                    // One order per invoice carrying the buyer (ageing joins
                    // invoices → orders for the buyer morph).
                    $orderId = (int) $connection->table('orders')->insertGetId([
                        'buyer_type' => 'App\\Models\\Distributor',
                        'buyer_id' => $buyerId,
                        'seller_type' => $sellerType,
                        'seller_id' => $sellerId,
                        'status' => 'confirmed',
                        'order_type' => 'standard',
                        'total' => 0,
                        'created_at' => $asOf->subDays($ageDays)->format('Y-m-d H:i:s'),
                        'updated_at' => $now,
                    ]);

                    $invoiceDate = $asOf->subDays($ageDays);
                    $invoiceId = (int) $connection->table('invoices')->insertGetId([
                        'order_id' => $orderId,
                        'seller_type' => $sellerType,
                        'seller_id' => $sellerId,
                        'series' => 'AGG',
                        'number' => $numberSeq++,
                        'amount' => number_format($amtCents / 100, 2, '.', ''),
                        'overdue' => false,
                        'created_at' => $invoiceDate->format('Y-m-d H:i:s'),
                        'updated_at' => $now,
                    ]);

                    // Allocate allocPct of the invoice amount (clamped so a
                    // single allocation never exceeds the amount — matches the
                    // allocation ceiling invariant; >100% simply fully pays it).
                    $allocCents = min($amtCents, (int) floor($amtCents * $allocPct / 100));
                    if ($allocCents > 0) {
                        $paymentId = (int) $connection->table('payments')->insertGetId([
                            'buyer_type' => 'App\\Models\\Distributor',
                            'buyer_id' => $buyerId,
                            'amount' => number_format($allocCents / 100, 2, '.', ''),
                            'method' => 'cash',
                            'reference' => null,
                            'paid_at' => $invoiceDate->toDateString(),
                            'recorded_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $connection->table('payment_allocations')->insert([
                            'payment_id' => $paymentId,
                            'invoice_id' => $invoiceId,
                            'amount' => number_format($allocCents / 100, 2, '.', ''),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    // Reference: outstanding = max(amount − allocated, 0), aged
                    // by DATEDIFF(asOf, invoiceDate) == ageDays, into one bucket.
                    $outstandingCents = max($amtCents - $allocCents, 0);

                    if (! isset($ref[$buyerId])) {
                        $ref[$buyerId] = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0, 'total' => 0];
                    }
                    $ref[$buyerId][$bucketKeyFor($ageDays)] += $outstandingCents;
                    $ref[$buyerId]['total'] += $outstandingCents;
                }

                // The window end is the as-of date; `from` is irrelevant to the
                // ageing report but set to a wide lower bound for completeness.
                $result = $service->run(
                    ReportQuerySpec::fromArray([
                        'type' => 'outstanding_ageing',
                        'filters' => [
                            'from' => $asOf->subYears(2)->toDateString(),
                            'to' => $asOf->toDateString(),
                        ],
                        'limit' => 500,
                    ]),
                    aggCorrAdminContext(),
                );

                // One report row per buyer that has invoices.
                expect($result->count())->toBe(count($ref));

                foreach ($result->rows as $row) {
                    $buyerId = (int) $row['buyer_id'];
                    expect($ref)->toHaveKey($buyerId);

                    $expected = $ref[$buyerId];

                    $current = (int) round(((float) $row['bucket_current']) * 100);
                    $b1_30 = (int) round(((float) $row['bucket_1_30']) * 100);
                    $b31_60 = (int) round(((float) $row['bucket_31_60']) * 100);
                    $b61_90 = (int) round(((float) $row['bucket_61_90']) * 100);
                    $b90 = (int) round(((float) $row['bucket_90_plus']) * 100);
                    $total = (int) round(((float) $row['total_outstanding']) * 100);

                    // Aggregate correctness: each bucket == independent reference
                    // (R28.5).
                    expect($current)->toBe($expected['current']);
                    expect($b1_30)->toBe($expected['1_30']);
                    expect($b31_60)->toBe($expected['31_60']);
                    expect($b61_90)->toBe($expected['61_90']);
                    expect($b90)->toBe($expected['90_plus']);
                    expect($total)->toBe($expected['total']);

                    // Reconciliation: the five buckets sum to the total
                    // (Property 29 reconciliation clause).
                    expect($current + $b1_30 + $b31_60 + $b61_90 + $b90)->toBe($total);
                }
            });
        });
    } finally {
        aggCorrTeardownTenants($registry);
    }
});
