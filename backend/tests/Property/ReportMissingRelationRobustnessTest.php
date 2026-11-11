<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Tenant;
use App\Reports\Definitions\OrderStatusReportDefinition;
use App\Reports\Definitions\StockLedgerReportDefinition;
use App\Reports\Definitions\StockOnHandReportDefinition;
use App\Auth\Roles;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Services\ReportCache;
use App\Services\ReportQueryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 11.4 — Property 30: Report robustness to missing relations
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 30: Report robustness to
| missing relations
|
| Validates: Requirements 29.7
|
| Property statement (design.md §Property 30):
|   For any report input in which some related records are missing or
|   soft-deleted, the report completes without error and omits the missing
|   relations.
|
|   - R29.7  IF a report references a missing or soft-deleted related record,
|            THEN THE Report_Engine SHALL produce the report without error and
|            silently omit the missing relation.
|
| Operationally (task 11.1 / 11.6), every report whose rows carry a related
| label builds its base query with a LEFT JOIN + COALESCE on the relation, so a
| row whose related record is absent is KEPT (never dropped, never erroring)
| with a null/placeholder label rather than being inner-joined away. This proof
| exercises that guarantee across the three LEFT-JOIN reports in the catalog:
|
|   • order_status   — each Order LEFT JOINs its `invoices` row; an order with
|                      no invoice is a genuinely ABSENT relation (no dangling
|                      key needed), and must still list with a null invoice
|                      number (OrderStatusReportDefinition, R28.7, R29.7).
|   • stock_on_hand   — each stock row LEFT JOINs its `products` row; a stock
|                      row whose product was hard-deleted (a dangling
|                      product_id) must still appear with a null product label
|                      (StockOnHandReportDefinition, R28.1, R29.7).
|   • stock_ledger   — each movement LEFT JOINs its `products` row; a movement
|                      whose product was hard-deleted must still list with a
|                      null product label (StockLedgerReportDefinition, R28.2,
|                      R29.7).
|
| The dangling-product scenarios mirror the hard-deleted-relation fixtures the
| sibling feature suites use (ReportQueryServiceTest / ReportCatalogTest). To
| stay installable regardless of the environment's FK enforcement, the dangling
| rows are inserted with FOREIGN_KEY_CHECKS temporarily relaxed, exactly
| modelling a product that was removed after the stock/ledger row referenced it.
|
| Generation uses innmind/black-box (NOT hand-rolled) via the shared
| static::forAll(), drawing the mandated minimum of 100 generated scenarii per
| proof and shrinking any counterexample to a minimal failing input. Each
| generated scenario is a collection of rows, each independently flagged
| "relation present" or "relation missing", so the generator freely explores
| all-present, all-missing, and every mix in between.
|
| DB-GATED. Provisioning a real per-tenant MySQL database and running the report
| query (which LEFT JOINs + COALESCEs SQL-side) needs a reachable MySQL server;
| when MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'). All work runs inside $tenant->run() so every
| row is confined to the active tenant database (physical isolation, R28.10).
*/

/**
 * Provision + migrate an isolated tenant for this proof and register it for
 * teardown. File-unique `missingRel...` helper names avoid colliding with the
 * sibling Property files Pest loads alongside this one.
 *
 * @param  array<int, Tenant>  $registry
 */
function missingRelProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'relr'.$suffix,
        'subdomain' => 'relr-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'relr-'.$suffix.'.example.test']);

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
function missingRelTeardownTenants(array $registry): void
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
 */
function missingRelSeedProduct(string $sku): int
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
 * Insert rows with FK enforcement temporarily relaxed, so a dangling reference
 * (a product that was hard-deleted after the referencing row was written) can
 * be modelled regardless of the environment's FK settings. Restores the prior
 * setting afterwards.
 *
 * @param  callable():void  $insert
 */
function missingRelWithoutForeignKeys(callable $insert): void
{
    $connection = DB::connection('tenant');
    $connection->statement('SET FOREIGN_KEY_CHECKS=0');

    try {
        $insert();
    } finally {
        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

/**
 * A Company_Admin report context: tenant-wide report scope, so no buyer
 * confinement narrows the generated rows out of the result (R28.10).
 */
function missingRelAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * The shared report service with the three LEFT-JOIN report definitions this
 * proof exercises registered.
 */
function missingRelService(?ReportCache $cache = null): ReportQueryService
{
    return new ReportQueryService([
        new StockOnHandReportDefinition,
        new StockLedgerReportDefinition,
        new OrderStatusReportDefinition,
    ], $cache);
}

it('keeps every report row with a null label when a related record is missing, never dropping a row or erroring (R29.7)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = missingRelProvisionTenant($registry);

        $tenant->run(function () {
            // The tenant's single Company owner (R1.5) plus one stock point, so
            // generated rows always resolve a present stock-point relation and
            // the ONLY relation we vary is the product (stock/ledger) or the
            // invoice (orders).
            $company = Company::create(['name' => 'Missing Relation Co']);
            $pointId = (int) $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0])->id;

            // A pool of real products the "present" rows reference. "Missing"
            // rows reference ids guaranteed never to exist.
            $products = [
                missingRelSeedProduct('REL-A'),
                missingRelSeedProduct('REL-B'),
                missingRelSeedProduct('REL-C'),
            ];
            $missingProductId = 9_000_000; // no product with this id is ever seeded

            // Hold the report aggregate cache explicitly so it can be
            // invalidated between generated scenarios. This proof reseeds the
            // tables directly (not through a write path that would bump the
            // cache generation), so without an explicit invalidation a later
            // iteration whose resolved spec matches an earlier one would be
            // served the earlier iteration's cached aggregate (R29.8). Cache
            // correctness itself is proven separately by Property 31.
            $cache = new ReportCache;
            $service = missingRelService($cache);
            $context = missingRelAdminContext();
            $ownerType = $company->getMorphClass();
            $ownerId = (int) $company->getKey();

            static::forAll(
                // A non-empty collection of rows. Each row is a tuple:
                //   [productIdx, relationPresent]
                // relationPresent=true  → reference a real product / attach an
                //                          invoice (relation present).
                // relationPresent=false → reference a dangling product id /
                //                          leave the order un-invoiced (relation
                //                          missing — the R29.7 case).
                // Drawing the flag independently per row lets the generator
                // reach all-present, all-missing, and every mix.
                Set::sequence(
                    Set::compose(
                        static fn (int $p, bool $present): array => [$p, $present],
                        Set::integers()->between(0, 2),
                        Set::either(Set::of(true), Set::of(false)),
                    ),
                )->between(1, 10),
            )->then(function (array $rows) use (
                $service, $cache, $context, $ownerType, $ownerId, $pointId, $products, $missingProductId
            ) {
                $connection = DB::connection('tenant');

                // Isolate each generated scenario: clear the tables this
                // iteration writes to so each report reflects only THIS run.
                $connection->table('stock')->delete();
                $connection->table('stock_ledger')->delete();
                $connection->table('invoices')->delete();
                $connection->table('orders')->delete();

                // Invalidate this tenant's cached report aggregates so each
                // report below is recomputed against the rows seeded for THIS
                // scenario, never a prior iteration's cached result (R29.8).
                $cache->invalidate();

                $now = now();

                // ---------------------------------------------------------
                // stock_on_hand & stock_ledger: one (product, point) slot per
                // generated row. A "present" row references a real product; a
                // "missing" row references a dangling product id (a product
                // hard-deleted after the row was written).
                // ---------------------------------------------------------
                $stockRows = [];
                $ledgerRows = [];
                // Track, per inserted stock/ledger key, whether its product
                // relation is present, for the per-row label assertions.
                $expectStockLabelNull = [];   // product_id => bool (expect null label)
                $expectLedgerLabelNull = [];  // ledger reference key => bool

                // A stock row IS the (owner, product, point) tuple (unique
                // key), so each generated row must claim a DISTINCT product
                // slot. We rotate real products and use distinct dangling ids
                // for missing rows so no two rows collide on the unique key.
                $usedStockProduct = [];
                $danglingSeq = 0;

                foreach ($rows as [$pIdx, $present]) {
                    $productId = $present
                        ? $products[$pIdx]
                        : ($missingProductId + $danglingSeq++);

                    if (isset($usedStockProduct[$productId])) {
                        // keep one stock row per product (unique owner/product/point)
                        continue;
                    }
                    $usedStockProduct[$productId] = true;

                    $stockRows[] = [
                        'stockable_type' => $ownerType, 'stockable_id' => $ownerId,
                        'product_id' => $productId, 'stock_point_id' => $pointId,
                        'quantity' => '5.000', 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $ledgerRows[] = [
                        'stockable_type' => $ownerType, 'stockable_id' => $ownerId,
                        'product_id' => $productId, 'stock_point_id' => $pointId,
                        'movement_type' => 'received', 'quantity' => '5.000', 'balance_after' => '5.000',
                        'created_at' => $now, 'updated_at' => $now,
                    ];

                    $expectStockLabelNull[$productId] = ! $present;
                    $expectLedgerLabelNull[$productId] = ! $present;
                }

                // Insert the stock/ledger rows with FK checks relaxed so the
                // dangling-product rows (modelling a hard-deleted product) are
                // accepted exactly as they would be once a product is removed.
                missingRelWithoutForeignKeys(function () use ($connection, $stockRows, $ledgerRows) {
                    if ($stockRows !== []) {
                        $connection->table('stock')->insert($stockRows);
                    }
                    if ($ledgerRows !== []) {
                        $connection->table('stock_ledger')->insert($ledgerRows);
                    }
                });

                // ---------------------------------------------------------
                // order_status: one Order per generated row. A "present" row
                // gets an invoice (relation present); a "missing" row stays
                // un-invoiced (relation genuinely ABSENT — no dangling key).
                // ---------------------------------------------------------
                $expectOrderInvoiceNull = []; // order_id => bool (expect null invoice number)
                $invoiceNumberSeq = 1;

                foreach ($rows as [$pIdx, $present]) {
                    $orderId = (int) $connection->table('orders')->insertGetId([
                        'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7,
                        'seller_type' => $ownerType, 'seller_id' => $ownerId,
                        'status' => 'confirmed', 'order_type' => 'standard', 'total' => '10.00',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);

                    if ($present) {
                        $connection->table('invoices')->insert([
                            'order_id' => $orderId, 'seller_type' => $ownerType, 'seller_id' => $ownerId,
                            'series' => 'FY', 'number' => $invoiceNumberSeq++, 'amount' => '10.00',
                            'overdue' => false, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }

                    $expectOrderInvoiceNull[$orderId] = ! $present;
                }

                // ===========================================================
                // ASSERTIONS — each report completes WITHOUT error, keeps ALL
                // rows (never dropped), and nulls the label of any missing
                // relation while preserving a present one (R29.7).
                // Page size is larger than any generated scenario (<= 10 rows
                // per report) so the single page holds the full result set.
                // ===========================================================

                // The three reports below filter by `created_at` (stock_on_hand
                // bounds its snapshot by `created_at <= to`; stock_ledger and
                // order_status bound by the resolved range). The seeded rows are
                // dated `now()`, so pass an explicit window covering today rather
                // than relying on the previous-calendar-month default (R29.2),
                // which precedes these rows and would exclude every one of them.
                $nowWindow = [
                    'from' => now()->startOfDay()->toDateTimeString(),
                    'to' => now()->endOfDay()->toDateTimeString(),
                ];

                // ---- stock_on_hand -----------------------------------------
                $onHand = $service->run(
                    ReportQuerySpec::fromArray(['type' => 'stock_on_hand', 'filters' => $nowWindow, 'limit' => 100]),
                    $context,
                );
                // Not dropped: one report row per seeded stock row.
                expect($onHand->count())->toBe(count($stockRows));
                foreach ($onHand->rows as $row) {
                    $pid = (int) $row['product_id'];
                    if (($expectStockLabelNull[$pid] ?? false) === true) {
                        // Missing product relation → null label (omitted).
                        expect($row['product_sku'])->toBeNull();
                        expect($row['product_name'])->toBeNull();
                    } else {
                        // Present relation → real label preserved.
                        expect($row['product_sku'])->not->toBeNull();
                    }
                    // The present stock-point relation is never nulled.
                    expect($row['stock_point_name'])->toBe('Main');
                }

                // ---- stock_ledger ------------------------------------------
                $ledger = $service->run(
                    ReportQuerySpec::fromArray(['type' => 'stock_ledger', 'filters' => $nowWindow, 'limit' => 100]),
                    $context,
                );
                expect($ledger->count())->toBe(count($ledgerRows));
                foreach ($ledger->rows as $row) {
                    $pid = (int) $row['product_id'];
                    if (($expectLedgerLabelNull[$pid] ?? false) === true) {
                        expect($row['product_sku'])->toBeNull();
                        expect($row['product_name'])->toBeNull();
                    } else {
                        expect($row['product_sku'])->not->toBeNull();
                    }
                    expect($row['stock_point_name'])->toBe('Main');
                    // The movement itself (the row) is always present.
                    expect($row['movement_type'])->toBe('received');
                }

                // ---- order_status ------------------------------------------
                $orders = $service->run(
                    ReportQuerySpec::fromArray(['type' => 'order_status', 'filters' => $nowWindow, 'limit' => 100]),
                    $context,
                );
                // Not dropped: one report row per seeded order, invoiced or not.
                expect($orders->count())->toBe(count($expectOrderInvoiceNull));
                foreach ($orders->rows as $row) {
                    $oid = (int) $row['id'];
                    if (($expectOrderInvoiceNull[$oid] ?? false) === true) {
                        // Missing invoice relation → null challan reference.
                        expect($row['invoice_number'])->toBeNull();
                        expect($row['invoice_series'])->toBeNull();
                    } else {
                        expect($row['invoice_number'])->not->toBeNull();
                    }
                    // The order (the row) is always present with its status.
                    expect($row['status'])->toBe('confirmed');
                }
            });
        });
    } finally {
        missingRelTeardownTenants($registry);
    }
});
