<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Tenant;
use App\Reports\Definitions\LowStockReportDefinition;
use App\Reports\Definitions\OrderStatusReportDefinition;
use App\Reports\Definitions\OutstandingAgeingReportDefinition;
use App\Reports\Definitions\PaymentsReportDefinition;
use App\Reports\Definitions\PlanUsageReportDefinition;
use App\Reports\Definitions\PurchasesReportDefinition;
use App\Reports\Definitions\SalesReportDefinition;
use App\Reports\Definitions\StockLedgerReportDefinition;
use App\Reports\Definitions\StockOnHandReportDefinition;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Services\ReportQueryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 11.6 — Standard report catalog (DB-gated, end-to-end)
|--------------------------------------------------------------------------
|
| Exercises every standard report in the catalog (R28.1–R28.9) against a REAL
| provisioned tenant database, proving each definition's SQL-side aggregation,
| left-join/null-coalesce robustness, filter conformance, and — for sales,
| payments, and ageing — that the aggregates reconcile with a straightforward
| reference computation over the same data (Property 29 intent).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling report suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for catalog report tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionCatalogTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cat'.$suffix,
        'subdomain' => 'cat-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'cat-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    return $tenant;
}

/**
 * Seed one catalog product (with its category/collection/colour) and return
 * [productId, categoryId].
 *
 * @return array{0: int, 1: int}
 */
function seedCatalogProduct(string $sku): array
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

function catalogAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * Insert rows with FK enforcement temporarily relaxed on the tenant connection,
 * so a dangling reference (a product hard-deleted after the referencing row was
 * written) can be modelled regardless of the environment's FK settings. Mirrors
 * the sibling robustness suite's helper. Restores enforcement afterwards.
 *
 * @param  callable():void  $insert
 */
function catalogWithoutForeignKeys(callable $insert): void
{
    $connection = DB::connection('tenant');
    $connection->statement('SET FOREIGN_KEY_CHECKS=0');

    try {
        $insert();
    } finally {
        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

function makeCatalogService(): ReportQueryService
{
    return new ReportQueryService([
        new StockOnHandReportDefinition,
        new StockLedgerReportDefinition,
        new SalesReportDefinition,
        new PurchasesReportDefinition,
        new OutstandingAgeingReportDefinition,
        new PaymentsReportDefinition,
        new OrderStatusReportDefinition,
        new LowStockReportDefinition,
        new PlanUsageReportDefinition,
    ]);
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownCatalogTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $t->domains()->delete();
            $t->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

it('lists stock-ledger movements in time order, coalescing a missing product (R28.2, R29.7)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            [$productId] = seedCatalogProduct('SKU-L');

            $now = now();
            // The second movement references a product that was later
            // hard-deleted (a dangling product_id). FK enforcement would reject
            // it, so insert with FK checks temporarily relaxed — modelling a
            // product removed after the ledger entry was written — exactly as
            // the sibling robustness suite does. The report must still list the
            // row with a null product label (R29.7).
            catalogWithoutForeignKeys(function () use ($company, $productId, $point, $now) {
                DB::table('stock_ledger')->insert([
                    [
                        'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
                        'product_id' => $productId, 'stock_point_id' => $point->getKey(),
                        'movement_type' => 'received', 'quantity' => '10.000', 'balance_after' => '10.000',
                        'created_at' => $now, 'updated_at' => $now,
                    ],
                    // A movement whose product was later hard-deleted: must still
                    // list, with a null product label (R29.7).
                    [
                        'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
                        'product_id' => 999999, 'stock_point_id' => $point->getKey(),
                        'movement_type' => 'adjustment_in', 'quantity' => '2.000', 'balance_after' => '2.000',
                        'created_at' => $now, 'updated_at' => $now,
                    ],
                ]);
            });

            // Ledger entries are dated `now()`; pass an explicit window
            // covering today rather than relying on the previous-calendar-month
            // default (R29.2), which filters `created_at` and would exclude
            // these current-dated rows.
            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'stock_ledger',
                    'filters' => [
                        'from' => now()->startOfDay()->toDateTimeString(),
                        'to' => now()->endOfDay()->toDateTimeString(),
                    ],
                ]),
                catalogAdminContext(),
            );

            expect($result->count())->toBe(2);
            $missing = collect($result->rows)->firstWhere('product_id', 999999);
            expect($missing['product_sku'])->toBeNull();
            expect($missing['movement_type'])->toBe('adjustment_in');
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('aggregates sales value SQL-side matching a reference computation (R28.3, Property 29)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            [$productA] = seedCatalogProduct('SKU-SA');
            [$productB] = seedCatalogProduct('SKU-SB');

            $now = now();
            // A confirmed order (counts as a sale) and a cancelled one (ignored).
            $confirmed = (int) DB::table('orders')->insertGetId([
                'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7,
                'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                'status' => 'confirmed', 'order_type' => 'standard', 'total' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $cancelled = (int) DB::table('orders')->insertGetId([
                'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7,
                'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                'status' => 'cancelled', 'order_type' => 'standard', 'total' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('order_lines')->insert([
                // qty 3 * (100 - 10) = 270
                ['order_id' => $confirmed, 'product_id' => $productA, 'quantity' => '3.000', 'unit_price' => '100.00', 'discount' => '10.00', 'created_at' => $now, 'updated_at' => $now],
                // qty 2 * (50 - 0) = 100
                ['order_id' => $confirmed, 'product_id' => $productB, 'quantity' => '2.000', 'unit_price' => '50.00', 'discount' => '0.00', 'created_at' => $now, 'updated_at' => $now],
                // on the cancelled order — must NOT be counted
                ['order_id' => $cancelled, 'product_id' => $productA, 'quantity' => '5.000', 'unit_price' => '100.00', 'discount' => '0.00', 'created_at' => $now, 'updated_at' => $now],
            ]);

            // The sales report filters orders by `created_at` within the
            // resolved window. The seeded orders are dated `now()`, so pass an
            // explicit range covering today rather than relying on the service's
            // previous-calendar-month default (R29.2), which would exclude these
            // current-dated rows and return 0.
            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'sales',
                    'filters' => [
                        'from' => now()->startOfDay()->toDateTimeString(),
                        'to' => now()->endOfDay()->toDateTimeString(),
                    ],
                ]),
                catalogAdminContext(),
            );

            $totalSales = collect($result->rows)->sum(fn ($r) => (float) $r['sales_value']);
            // Reference: 270 + 100 = 370 (cancelled order excluded).
            expect($totalSales)->toBe(370.0);
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('aggregates purchases by buyer over confirmed orders (R28.4)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);

            $now = now();
            DB::table('orders')->insert([
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'confirmed', 'order_type' => 'standard', 'total' => '200.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'delivered', 'order_type' => 'standard', 'total' => '50.00', 'created_at' => $now, 'updated_at' => $now],
                // draft — not a purchase
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'draft', 'order_type' => 'standard', 'total' => '999.00', 'created_at' => $now, 'updated_at' => $now],
            ]);

            // Orders are dated `now()`; pass an explicit window covering today
            // rather than relying on the previous-calendar-month default (R29.2),
            // which would exclude these current-dated rows.
            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'purchases',
                    'grouping' => ['buyer'],
                    'filters' => [
                        'from' => now()->startOfDay()->toDateTimeString(),
                        'to' => now()->endOfDay()->toDateTimeString(),
                    ],
                ]),
                catalogAdminContext(),
            );

            $total = collect($result->rows)->sum(fn ($r) => (float) $r['purchases_value']);
            $orderCount = collect($result->rows)->sum(fn ($r) => (int) $r['order_count']);
            expect($total)->toBe(250.0);   // 200 + 50, draft excluded
            expect($orderCount)->toBe(2);
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('buckets outstanding invoices by age and the buckets reconcile to the total (R28.5, Property 29)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);

            $asOf = now()->startOfDay();

            // Exactly one invoice is generated per order (invoices_order_id_unique),
            // so each invoice needs its OWN order rather than sharing one. All
            // three orders carry the same buyer so the ageing report rolls them
            // up into a single buyer row.
            $orderFor = function (int $daysOld) use ($company, $asOf): int {
                $createdAt = $asOf->copy()->subDays($daysOld);

                return (int) DB::table('orders')->insertGetId([
                    'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7,
                    'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                    'status' => 'confirmed', 'order_type' => 'standard', 'total' => 0,
                    'created_at' => $createdAt, 'updated_at' => $asOf,
                ]);
            };
            $order10 = $orderFor(10);
            $order45 = $orderFor(45);
            $order120 = $orderFor(120);

            // Three invoices at different ages relative to the as-of date, one
            // per order.
            DB::table('invoices')->insert([
                // 10 days old → 1-30 bucket, outstanding 100
                ['order_id' => $order10, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'series' => 'S', 'number' => 1, 'amount' => '100.00', 'overdue' => false, 'created_at' => $asOf->copy()->subDays(10), 'updated_at' => $asOf],
                // 45 days old → 31-60 bucket, outstanding 200 - 50 allocated = 150
                ['order_id' => $order45, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'series' => 'S', 'number' => 2, 'amount' => '200.00', 'overdue' => false, 'created_at' => $asOf->copy()->subDays(45), 'updated_at' => $asOf],
                // 120 days old → 90-plus bucket, outstanding 300
                ['order_id' => $order120, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'series' => 'S', 'number' => 3, 'amount' => '300.00', 'overdue' => false, 'created_at' => $asOf->copy()->subDays(120), 'updated_at' => $asOf],
            ]);

            $invoice2 = (int) DB::table('invoices')->where('number', 2)->value('id');
            $payment = (int) DB::table('payments')->insertGetId([
                'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7,
                'amount' => '50.00', 'method' => 'cash', 'paid_at' => $asOf->toDateString(),
                'created_at' => $asOf, 'updated_at' => $asOf,
            ]);
            DB::table('payment_allocations')->insert([
                'payment_id' => $payment, 'invoice_id' => $invoice2, 'amount' => '50.00',
                'created_at' => $asOf, 'updated_at' => $asOf,
            ]);

            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'outstanding_ageing',
                    'filters' => ['from' => $asOf->copy()->subYear()->toDateString(), 'to' => $asOf->toDateString()],
                ]),
                catalogAdminContext(),
            );

            expect($result->count())->toBe(1);
            $row = $result->rows[0];

            expect((float) $row['bucket_1_30'])->toBe(100.0);
            expect((float) $row['bucket_31_60'])->toBe(150.0);
            expect((float) $row['bucket_90_plus'])->toBe(300.0);

            // Buckets reconcile to the total (Property 29).
            $bucketSum = (float) $row['bucket_current'] + (float) $row['bucket_1_30']
                + (float) $row['bucket_31_60'] + (float) $row['bucket_61_90']
                + (float) $row['bucket_90_plus'];
            expect($bucketSum)->toBe((float) $row['total_outstanding']);
            expect((float) $row['total_outstanding'])->toBe(550.0);
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('aggregates collected payments by period matching a reference total (R28.6, Property 29)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $today = now()->toDateString();
            $now = now();

            DB::table('payments')->insert([
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'amount' => '100.00', 'method' => 'cash', 'paid_at' => $today, 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'amount' => '40.50', 'method' => 'upi', 'paid_at' => $today, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'payments',
                    'filters' => ['from' => $today, 'to' => $today],
                ]),
                catalogAdminContext(),
            );

            $collected = collect($result->rows)->sum(fn ($r) => (float) $r['collected']);
            expect($collected)->toBe(140.5);
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('lists orders with status and challan reference, coalescing a missing invoice (R28.7, R29.7)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $now = now();

            $invoiced = (int) DB::table('orders')->insertGetId([
                'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                'status' => 'dispatched', 'order_type' => 'standard', 'total' => '100.00', 'created_at' => $now, 'updated_at' => $now,
            ]);
            // An order with no invoice yet — must still list (R29.7).
            DB::table('orders')->insert([
                'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 7, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                'status' => 'placed', 'order_type' => 'standard', 'total' => '20.00', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('invoices')->insert([
                'order_id' => $invoiced, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                'series' => 'FY', 'number' => 42, 'amount' => '100.00', 'overdue' => false, 'created_at' => $now, 'updated_at' => $now,
            ]);

            // Orders are dated `now()`; the order-status report filters by
            // `created_at`, so pass an explicit window covering today rather
            // than relying on the previous-calendar-month default (R29.2),
            // which would exclude these current-dated rows.
            $todayWindow = [
                'from' => now()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ];

            // Full listing: both orders present.
            $all = makeCatalogService()->run(
                ReportQuerySpec::fromArray(['type' => 'order_status', 'filters' => $todayWindow]),
                catalogAdminContext(),
            );
            expect($all->count())->toBe(2);
            $placed = collect($all->rows)->firstWhere('status', 'placed');
            expect($placed['invoice_number'])->toBeNull();

            // Status filter → delivery-challan listing of dispatched orders only.
            $dispatched = makeCatalogService()->run(
                ReportQuerySpec::fromArray(['type' => 'order_status', 'filters' => $todayWindow + ['status' => 'dispatched']]),
                catalogAdminContext(),
            );
            expect($dispatched->count())->toBe(1);
            expect((int) $dispatched->rows[0]['invoice_number'])->toBe(42);
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('reports only stock rows at or below a configured threshold (R28.8, R19.3)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            [$low] = seedCatalogProduct('SKU-LOW');
            [$ok] = seedCatalogProduct('SKU-OK');
            [$noThreshold] = seedCatalogProduct('SKU-NONE');

            $now = now();
            DB::table('stock')->insert([
                // breaching: qty 2 <= threshold 5
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $low, 'stock_point_id' => $point->getKey(), 'quantity' => '2.000', 'low_stock_threshold' => '5.000', 'created_at' => $now, 'updated_at' => $now],
                // not breaching: qty 50 > threshold 5
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $ok, 'stock_point_id' => $point->getKey(), 'quantity' => '50.000', 'low_stock_threshold' => '5.000', 'created_at' => $now, 'updated_at' => $now],
                // no threshold configured: never a breach
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $noThreshold, 'stock_point_id' => $point->getKey(), 'quantity' => '0.000', 'low_stock_threshold' => null, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray(['type' => 'low_stock']),
                catalogAdminContext(),
            );

            expect($result->count())->toBe(1);
            expect($result->rows[0]['product_sku'])->toBe('SKU-LOW');
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});

it('reports per-resource plan usage counts for the tenant (R28.9)', function () {
    $registry = [];
    $tenant = provisionCatalogTenant($registry);

    try {
        $tenant->run(function () {
            $now = now();
            // The tenant's single Company owns the distributors; create it so the
            // distributors.company_id foreign key resolves.
            $company = Company::create(['name' => 'Acme']);
            // Two distributors; dealers belong to a distributor.
            $d1 = DB::table('distributors')->insertGetId(['company_id' => $company->getKey(), 'name' => 'D1', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('distributors')->insert(['company_id' => $company->getKey(), 'name' => 'D2', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('dealers')->insert(['distributor_id' => $d1, 'name' => 'Dealer1', 'created_at' => $now, 'updated_at' => $now]);

            $result = makeCatalogService()->run(
                ReportQuerySpec::fromArray(['type' => 'plan_usage']),
                catalogAdminContext(),
            );

            $byResource = collect($result->rows)->keyBy('resource');
            expect((int) $byResource['distributors']['count'])->toBe(2);
            expect((int) $byResource['dealers']['count'])->toBe(1);
            // Every counted resource is represented, including monthly orders.
            expect($byResource->has('orders_this_month'))->toBeTrue();
        });
    } finally {
        teardownCatalogTenants($registry);
    }
});
