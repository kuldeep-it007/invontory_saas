<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\OrderStatus;
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
use App\Services\DashboardService;
use App\Services\InventoryViewService;
use App\Services\QuotaService;
use App\Services\ReportQueryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 15.1 — Dashboards & inventory views (DB-gated, end-to-end)
|--------------------------------------------------------------------------
|
| Exercises the company master dashboard (R33.1) and the stock-on-hand,
| item-detail, and pending-orders inventory views (R33.2) against a REAL
| provisioned tenant database, proving the dashboard metrics reconcile with the
| seeded data and the pending-orders view lists exactly the orders placed but
| not yet confirmed or fulfilled.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the report catalog suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for dashboard tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionDashboardTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'dash'.$suffix,
        'subdomain' => 'dash-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'dash-'.$suffix.'.example.test']);
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
 * its id.
 */
function seedDashboardProduct(string $sku): int
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

function dashboardAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

function makeDashboardViewService(): InventoryViewService
{
    $reports = new ReportQueryService([
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

    return new InventoryViewService($reports);
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownDashboardTenants(array $registry): void
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

it('summarizes key operational metrics on the company master dashboard (R33.1)', function () {
    $registry = [];
    $tenant = provisionDashboardTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productLow = seedDashboardProduct('SKU-LOW');
            $productOk = seedDashboardProduct('SKU-OK');

            $now = now();

            // Orders across lifecycle states: 2 placed (pending), 1 confirmed,
            // 1 delivered (both count as sales), 1 cancelled (ignored by sales).
            DB::table('orders')->insert([
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'placed', 'order_type' => 'standard', 'total' => '10.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'placed', 'order_type' => 'standard', 'total' => '20.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'confirmed', 'order_type' => 'standard', 'total' => '100.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'delivered', 'order_type' => 'standard', 'total' => '50.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'cancelled', 'order_type' => 'standard', 'total' => '999.00', 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('payments')->insert([
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'amount' => '70.00', 'method' => 'cash', 'paid_at' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'amount' => '30.00', 'method' => 'upi', 'paid_at' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('stock')->insert([
                // breaching: qty 2 <= threshold 5
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $productLow, 'stock_point_id' => $point->getKey(), 'quantity' => '2.000', 'low_stock_threshold' => '5.000', 'created_at' => $now, 'updated_at' => $now],
                // healthy: qty 50 > threshold 5
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $productOk, 'stock_point_id' => $point->getKey(), 'quantity' => '50.000', 'low_stock_threshold' => '5.000', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $dashboard = new DashboardService(app(QuotaService::class));
            $snapshot = $dashboard->snapshot();

            // Order counts (R33.1) including the pending bucket = placed orders
            // not yet confirmed/fulfilled (R33.2).
            expect($snapshot['orders'][OrderStatus::PLACED])->toBe(2);
            expect($snapshot['orders']['pending'])->toBe(2);
            expect($snapshot['orders'][OrderStatus::CONFIRMED])->toBe(1);
            expect($snapshot['orders']['total'])->toBe(5);

            // Sales = confirmed + delivered totals; cancelled excluded.
            expect($snapshot['sales']['confirmed_orders'])->toBe(2);
            expect($snapshot['sales']['confirmed_value'])->toBe(150.0);

            // Collections = sum of payments.
            expect($snapshot['payments']['collected'])->toBe(100.0);

            // Inventory headline: 2 distinct products, 52 units on hand, 1 low.
            expect($snapshot['inventory']['products'])->toBe(2);
            expect($snapshot['inventory']['units_on_hand'])->toBe(52.0);
            expect($snapshot['inventory']['low_stock'])->toBe(1);

            // Plan usage block reused from QuotaService (R5.5).
            expect($snapshot['usage'])->toHaveKey('quotas');
        });
    } finally {
        teardownDashboardTenants($registry);
    }
});

it('lists current on-hand stock in the stock-on-hand view (R33.2)', function () {
    $registry = [];
    $tenant = provisionDashboardTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productA = seedDashboardProduct('SKU-A');
            $productB = seedDashboardProduct('SKU-B');

            $now = now();
            DB::table('stock')->insert([
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $productA, 'stock_point_id' => $point->getKey(), 'quantity' => '7.000', 'low_stock_threshold' => null, 'created_at' => $now, 'updated_at' => $now],
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $productB, 'stock_point_id' => $point->getKey(), 'quantity' => '3.000', 'low_stock_threshold' => null, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $result = makeDashboardViewService()->run(
                InventoryViewService::VIEW_STOCK_ON_HAND,
                dashboardAdminContext(),
                ['to' => now()->endOfDay()->toDateTimeString()],
            );

            expect($result->count())->toBe(2);
            $byProduct = collect($result->rows)->keyBy('product_id');
            expect((float) $byProduct[$productA]['quantity_on_hand'])->toBe(7.0);
            expect((float) $byProduct[$productB]['quantity_on_hand'])->toBe(3.0);
        });
    } finally {
        teardownDashboardTenants($registry);
    }
});

it('restricts the item-detail view to a single product and requires a product id (R33.2)', function () {
    $registry = [];
    $tenant = provisionDashboardTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productA = seedDashboardProduct('SKU-A');
            $productB = seedDashboardProduct('SKU-B');

            $now = now();
            DB::table('stock')->insert([
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $productA, 'stock_point_id' => $point->getKey(), 'quantity' => '7.000', 'low_stock_threshold' => null, 'created_at' => $now, 'updated_at' => $now],
                ['stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(), 'product_id' => $productB, 'stock_point_id' => $point->getKey(), 'quantity' => '3.000', 'low_stock_threshold' => null, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $service = makeDashboardViewService();

            $result = $service->run(
                InventoryViewService::VIEW_ITEM_DETAIL,
                dashboardAdminContext(),
                ['product_id' => $productA, 'to' => now()->endOfDay()->toDateTimeString()],
            );

            // Only the requested product's stock rows appear.
            expect($result->count())->toBe(1);
            expect((int) $result->rows[0]['product_id'])->toBe($productA);

            // Item-detail without a product id is rejected.
            expect(fn () => $service->specFor(InventoryViewService::VIEW_ITEM_DETAIL, []))
                ->toThrow(InvalidArgumentException::class);
        });
    } finally {
        teardownDashboardTenants($registry);
    }
});

it('lists only orders placed but not yet confirmed or fulfilled in the pending-orders view (R33.2)', function () {
    $registry = [];
    $tenant = provisionDashboardTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $now = now();

            // Two placed (pending) + one confirmed + one delivered + one
            // cancelled. The pending view must list ONLY the two placed orders.
            DB::table('orders')->insert([
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'placed', 'order_type' => 'standard', 'total' => '10.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'placed', 'order_type' => 'standard', 'total' => '20.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'confirmed', 'order_type' => 'standard', 'total' => '30.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'delivered', 'order_type' => 'standard', 'total' => '40.00', 'created_at' => $now, 'updated_at' => $now],
                ['buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1, 'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(), 'status' => 'cancelled', 'order_type' => 'standard', 'total' => '50.00', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $window = [
                'from' => now()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ];

            $result = makeDashboardViewService()->run(
                InventoryViewService::VIEW_PENDING_ORDERS,
                dashboardAdminContext(),
                $window,
            );

            expect($result->count())->toBe(2);
            foreach ($result->rows as $row) {
                expect($row['status'])->toBe(OrderStatus::PLACED);
            }

            // The pending contract cannot be widened by a caller-supplied status.
            $forced = makeDashboardViewService()->run(
                InventoryViewService::VIEW_PENDING_ORDERS,
                dashboardAdminContext(),
                $window + ['status' => 'confirmed'],
            );
            expect($forced->count())->toBe(2);
            foreach ($forced->rows as $row) {
                expect($row['status'])->toBe(OrderStatus::PLACED);
            }
        });
    } finally {
        teardownDashboardTenants($registry);
    }
});
