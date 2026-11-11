<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Task 7.11 — Stock ownership models (polymorphic) and migrations (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the stock ownership schema and models against a real provisioned
| tenant database:
|   • `stock_points` holds a polymorphic owner, display name, and sort order,
|     and a Company may own one or more (R13.4, R13.8);
|   • `stock` is a per-(owner, product, stock_point) quantity using
|     DECIMAL(15,3) with a nullable low_stock_threshold (R13.3);
|   • the (stockable, product_id, stock_point_id) key is unique — at most one
|     stock row per owner/product/stock point (R16, R28.1);
|   • the owner morph relation resolves back to the owning Company (R13.4).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the audit/auth suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for stock tests, returning it.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionStockTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'stock'.$suffix,
        'subdomain' => 'stock-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'stock-'.$suffix.'.example.test']);
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
 * Seed one catalog product (category -> collection -> colour -> product) inside
 * the active tenant database and return its id. Stock rows reference a real
 * product because the `stock.product_id` foreign key is enforced once the
 * catalog (task 7.4) is migrated. Uses the query builder directly so the test
 * does not depend on catalog models that belong to task 7.4.
 */
function seedStockTestProduct(string $sku = 'SKU-1'): int
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
 * @param  array<int, Tenant>  $registry
 */
function teardownStockTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

it('migrates the stock_points and stock tables into the tenant database', function () {
    $registry = [];
    $tenant = provisionStockTenant($registry);

    try {
        $tenant->run(function () {
            expect(Schema::hasTable('stock_points'))->toBeTrue();
            expect(Schema::hasTable('stock'))->toBeTrue();

            expect(Schema::hasColumns('stock_points', [
                'id', 'owner_type', 'owner_id', 'name', 'sort_order',
            ]))->toBeTrue();

            expect(Schema::hasColumns('stock', [
                'id', 'stockable_type', 'stockable_id', 'product_id',
                'stock_point_id', 'quantity', 'low_stock_threshold',
            ]))->toBeTrue();

            // Physical isolation: no tenant discriminator columns (R13, R38.3).
            expect(Schema::hasColumn('stock_points', 'tenant_id'))->toBeFalse();
            expect(Schema::hasColumn('stock', 'tenant_id'))->toBeFalse();
        });
    } finally {
        teardownStockTenants($registry);
    }
});

it('lets a Company own one or more named, ordered stock points', function () {
    $registry = [];
    $tenant = provisionStockTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);

            $main = $company->stockPoints()->create(['name' => 'Main Warehouse', 'sort_order' => 0]);
            $overflow = $company->stockPoints()->create(['name' => 'Overflow', 'sort_order' => 1]);

            expect(StockPoint::count())->toBe(2);
            expect($main->owner->is($company))->toBeTrue();

            $ordered = $company->stockPoints()->orderBy('sort_order')->pluck('name')->all();
            expect($ordered)->toBe(['Main Warehouse', 'Overflow']);

            // The overflow id is referenced so static analysers see the var used.
            expect($overflow->owner_type)->toBe(Company::class);
        });
    } finally {
        teardownStockTenants($registry);
    }
});

it('tracks a per-owner/product/stock-point quantity at DECIMAL(15,3) precision', function () {
    $registry = [];
    $tenant = provisionStockTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedStockTestProduct('SKU-PRECISION');

            $stock = new Stock([
                'product_id' => $productId,
                'stock_point_id' => $point->id,
                'quantity' => '123.456',
                'low_stock_threshold' => '10.500',
            ]);
            $stock->owner()->associate($company);
            $stock->save();

            $fresh = Stock::firstOrFail();
            expect((string) $fresh->quantity)->toBe('123.456');
            expect((string) $fresh->low_stock_threshold)->toBe('10.500');
            expect($fresh->owner->is($company))->toBeTrue();
            expect($fresh->stockPoint->is($point))->toBeTrue();
        });
    } finally {
        teardownStockTenants($registry);
    }
});

it('allows a null low_stock_threshold', function () {
    $registry = [];
    $tenant = provisionStockTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedStockTestProduct('SKU-NULLTHRESH');

            $stock = new Stock(['product_id' => $productId, 'stock_point_id' => $point->id, 'quantity' => '0']);
            $stock->owner()->associate($company);
            $stock->save();

            expect(Stock::firstOrFail()->low_stock_threshold)->toBeNull();
        });
    } finally {
        teardownStockTenants($registry);
    }
});

it('enforces a single stock row per owner, product, and stock point', function () {
    $registry = [];
    $tenant = provisionStockTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedStockTestProduct('SKU-UNIQUE');

            $first = new Stock(['product_id' => $productId, 'stock_point_id' => $point->id, 'quantity' => '5']);
            $first->owner()->associate($company);
            $first->save();

            $dup = new Stock(['product_id' => $productId, 'stock_point_id' => $point->id, 'quantity' => '9']);
            $dup->owner()->associate($company);

            expect(fn () => $dup->save())->toThrow(QueryException::class);
        });
    } finally {
        teardownStockTenants($registry);
    }
});
