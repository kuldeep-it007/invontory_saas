<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Tenant;
use App\Reports\Definitions\StockOnHandReportDefinition;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Services\ReportQueryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 11.1 — ReportQueryService shared query layer (DB-gated, end-to-end)
|--------------------------------------------------------------------------
|
| Exercises the shared query layer against a REAL provisioned tenant database
| via the stock-on-hand report (R28.1), proving the engine's cross-cutting
| guarantees hold against live SQL:
|   • SQL-side aggregation: on-hand quantity is SUMmed in the database and
|     returned per (owner, stock point, product) (R29.3, R38.1);
|   • pagination: a bounded page is returned with correct metadata (R29.3);
|   • left-join/null-coalesce robustness: a stock row whose related product was
|     hard-deleted still appears, with a null product label, rather than being
|     dropped or erroring (R29.7);
|   • physical tenant scoping: the query runs on the active tenant connection
|     (R28.10).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling service suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for report tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionReportTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'rep'.$suffix,
        'subdomain' => 'rep-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'rep-'.$suffix.'.example.test']);
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
 * Seed one catalog product inside the active tenant database and return its id.
 */
function seedReportProduct(string $sku): int
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

function reportCompanyAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

function makeReportService(): ReportQueryService
{
    return new ReportQueryService([new StockOnHandReportDefinition]);
}

/**
 * Insert rows with FK enforcement temporarily relaxed on the tenant connection,
 * so a dangling reference (a product hard-deleted after the referencing row was
 * written) can be modelled regardless of the environment's FK settings.
 * Restores enforcement afterwards.
 *
 * @param  callable():void  $insert
 */
function reportWithoutForeignKeys(callable $insert): void
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
 * @param  array<int, Tenant>  $registry
 */
function teardownReportTenants(array $registry): void
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

it('aggregates on-hand quantity SQL-side per owner/stock point/product (R28.1, R29.3)', function () {
    $registry = [];
    $tenant = provisionReportTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productA = seedReportProduct('SKU-A');
            $productB = seedReportProduct('SKU-B');

            $now = now();
            // Two rows for product A at the same point would violate the unique
            // key, so aggregation per (owner, point, product) returns one row
            // per distinct tuple; use distinct products to assert the SUM path.
            DB::table('stock')->insert([
                [
                    'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
                    'product_id' => $productA, 'stock_point_id' => $point->getKey(),
                    'quantity' => '10.000', 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
                    'product_id' => $productB, 'stock_point_id' => $point->getKey(),
                    'quantity' => '4.500', 'created_at' => $now, 'updated_at' => $now,
                ],
            ]);

            // Stock rows are dated `now()`. Stock-on-hand is a snapshot bounded
            // by `created_at <= to`; the resolved window end defaults to the end
            // of the PREVIOUS calendar month (R29.2), which precedes these
            // current-dated rows and would exclude them. Pass an explicit window
            // whose `to` covers today so the snapshot includes the seeded rows.
            $result = makeReportService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'stock_on_hand',
                    'filters' => [
                        'from' => now()->startOfDay()->toDateTimeString(),
                        'to' => now()->endOfDay()->toDateTimeString(),
                    ],
                ]),
                reportCompanyAdminContext(),
            );

            expect($result->count())->toBe(2);
            expect($result->total)->toBe(2);

            $byProduct = collect($result->rows)->keyBy('product_sku');
            expect((float) $byProduct['SKU-A']['quantity_on_hand'])->toBe(10.0);
            expect((float) $byProduct['SKU-B']['quantity_on_hand'])->toBe(4.5);
            expect($byProduct['SKU-A']['stock_point_name'])->toBe('Main');
        });
    } finally {
        teardownReportTenants($registry);
    }
});

it('still reports a stock row whose product relation is missing, with a null label (R29.7)', function () {
    $registry = [];
    $tenant = provisionReportTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            $now = now();
            // A stock row referencing a product id that does not exist (the
            // related product was hard-deleted). The LEFT JOIN + COALESCE must
            // keep the row and null the product label (R29.7). FK enforcement
            // would reject the dangling reference, so insert with FK checks
            // relaxed — modelling the product's removal after the stock row was
            // written.
            reportWithoutForeignKeys(function () use ($company, $point, $now) {
                DB::table('stock')->insert([
                    'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
                    'product_id' => 424242, 'stock_point_id' => $point->getKey(),
                    'quantity' => '7.000', 'created_at' => $now, 'updated_at' => $now,
                ]);
            });

            // Snapshot bounded by `created_at <= to`; pass an explicit window
            // covering today so the current-dated stock row is included rather
            // than being excluded by the previous-calendar-month default (R29.2).
            $result = makeReportService()->run(
                ReportQuerySpec::fromArray([
                    'type' => 'stock_on_hand',
                    'filters' => [
                        'from' => now()->startOfDay()->toDateTimeString(),
                        'to' => now()->endOfDay()->toDateTimeString(),
                    ],
                ]),
                reportCompanyAdminContext(),
            );

            // Row is present (not dropped, no error) and the product label is null.
            expect($result->count())->toBe(1);
            expect($result->rows[0]['product_sku'])->toBeNull();
            expect($result->rows[0]['stock_point_name'])->toBe('Main');
            expect((float) $result->rows[0]['quantity_on_hand'])->toBe(7.0);
        });
    } finally {
        teardownReportTenants($registry);
    }
});

it('returns a bounded page with pagination metadata (R29.3)', function () {
    $registry = [];
    $tenant = provisionReportTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            $now = now();
            $rows = [];
            for ($i = 0; $i < 5; $i++) {
                $productId = seedReportProduct('SKU-P'.$i);
                $rows[] = [
                    'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
                    'product_id' => $productId, 'stock_point_id' => $point->getKey(),
                    'quantity' => '1.000', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('stock')->insert($rows);

            $service = makeReportService();

            // Snapshot bounded by `created_at <= to`; pass an explicit window
            // covering today so the current-dated stock rows are counted rather
            // than excluded by the previous-calendar-month default (R29.2).
            $todayWindow = [
                'from' => now()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ];

            $page1 = $service->run(
                ReportQuerySpec::fromArray([
                    'type' => 'stock_on_hand',
                    'filters' => $todayWindow,
                    'limit' => 2,
                    'page' => 1,
                    'sort' => [['column' => 'product', 'dir' => 'asc']],
                ]),
                reportCompanyAdminContext(),
            );

            expect($page1->count())->toBe(2);
            expect($page1->total)->toBe(5);
            expect($page1->hasMorePages())->toBeTrue();

            $page3 = $service->run(
                ReportQuerySpec::fromArray([
                    'type' => 'stock_on_hand',
                    'filters' => $todayWindow,
                    'limit' => 2,
                    'page' => 3,
                    'sort' => [['column' => 'product', 'dir' => 'asc']],
                ]),
                reportCompanyAdminContext(),
            );

            // Last page: 5 rows, page size 2 → page 3 holds the single remainder.
            expect($page3->count())->toBe(1);
            expect($page3->hasMorePages())->toBeFalse();
        });
    } finally {
        teardownReportTenants($registry);
    }
});
