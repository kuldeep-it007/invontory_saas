<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Stock;
use App\Models\Tenant;
use App\Services\LowStockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Distributor stock visibility — low-stock report owner scoping
|--------------------------------------------------------------------------
|
| Feature: a Distributor viewing the low-stock report (GET /stock/low) sees
| ONLY the breaching stock rows held at a stock point it owns, never another
| owner's stock. Company-side roles remain tenant-wide (no owner scope).
|
| This exercises LowStockService::report($perPage, $ownerType, $ownerId), which
| force-scopes to the given polymorphic owner via Stock::scopeOwnedBy() when
| both owner params are set, mirroring the reporting engine's buyer-scoping.
|
| DB-GATED. Needs a reachable MySQL server to provision a real per-tenant
| database; skips gracefully when MySQL is unreachable. All work runs inside
| $tenant->run() so stock is confined to the active tenant database.
*/

/**
 * Provision + migrate an isolated tenant for this proof and register it for
 * teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function distVisProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'dvis'.$suffix,
        'subdomain' => 'dvis-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'dvis-'.$suffix.'.example.test']);

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
function distVisTeardownTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (Throwable $e) {
            // best-effort cleanup
        }
    }
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 */
function distVisSeedProduct(string $sku): int
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
 * Create one breaching stock row (quantity below threshold) for the given
 * polymorphic owner at one of its stock points, and return the stock id.
 */
function distVisSeedBreachingStock(Model $owner, int $stockPointId, int $productId): int
{
    $stock = (new Stock)->forceFill([
        'stockable_type' => $owner->getMorphClass(),
        'stockable_id' => $owner->getKey(),
        'product_id' => $productId,
        'stock_point_id' => $stockPointId,
        'quantity' => '1.000',
        'low_stock_threshold' => '5.000',
    ]);
    $stock->save();

    return (int) $stock->id;
}

it('confines a distributor low-stock report to its own stock point, excluding company and other-distributor stock', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = distVisProvisionTenant($registry);

        $tenant->run(function () {
            $service = new LowStockService;

            // A company (seller) and two distributors (buyers) in the tenant.
            $company = Company::create(['name' => 'Vis Co']);
            $distA = Distributor::create(['company_id' => $company->id, 'name' => 'Dist A']);
            $distB = Distributor::create(['company_id' => $company->id, 'name' => 'Dist B']);

            // Each owner holds a stock point it owns.
            $companyPoint = $company->stockPoints()->create(['name' => 'Co Main', 'sort_order' => 0])->id;
            $pointA = DB::table('stock_points')->insertGetId([
                'owner_type' => $distA->getMorphClass(), 'owner_id' => $distA->id,
                'name' => 'A Main', 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $pointB = DB::table('stock_points')->insertGetId([
                'owner_type' => $distB->getMorphClass(), 'owner_id' => $distB->id,
                'name' => 'B Main', 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $product = distVisSeedProduct('VIS-A');

            // One breaching row per owner.
            $companyStockId = distVisSeedBreachingStock($company, $companyPoint, $product);
            $stockAId = distVisSeedBreachingStock($distA, $pointA, $product);
            $stockBId = distVisSeedBreachingStock($distB, $pointB, $product);

            // Distributor A sees ONLY its own breaching row.
            $idsForA = collect($service->report(100, $distA->getMorphClass(), (int) $distA->id)->items())
                ->map(fn (Stock $s) => (int) $s->id)
                ->all();

            expect($idsForA)->toBe([$stockAId]);

            // Company-side (no owner scope) sees ALL three breaching rows.
            $idsTenantWide = collect($service->report(100)->items())
                ->map(fn (Stock $s) => (int) $s->id)
                ->sort()
                ->values()
                ->all();

            $expectedAll = collect([$companyStockId, $stockAId, $stockBId])
                ->sort()->values()->all();

            expect($idsTenantWide)->toBe($expectedAll);

            // A distributor with no resolvable binding (id 0) sees nothing.
            $idsNoBinding = $service->report(100, $distA->getMorphClass(), 0)->items();
            expect($idsNoBinding)->toBe([]);
        });
    } finally {
        distVisTeardownTenants($registry);
    }
});
