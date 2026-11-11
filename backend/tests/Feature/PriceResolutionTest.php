<?php

declare(strict_types=1);

use App\Models\Discount;
use App\Models\Price;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\PriceResolutionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 7.8 — Pricing & discount resolution against the tenant DB (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises PriceResolutionService::resolveForProduct() against a real
| provisioned tenant database:
|   • a Product-level price OVERRIDES a Collection-level price (R12.1, R12.4);
|   • the Collection-level price is the fallback when no product price exists;
|   • the collection markup/MUP raises the base (R12.2);
|   • active customer + online discounts apply cumulatively, buyer-scoped
|     customer discounts only to the matching Buyer (R12.5);
|   • the resolved price is never negative and tagged with the tenant currency
|     (R12.4, R39.1).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the catalog/stock suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * @param  array<int, Tenant>  $registry
 */
function provisionPricingTenant(array &$registry, string $currency = 'EUR'): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'price'.$suffix,
        'subdomain' => 'price-'.$suffix,
        'currency' => $currency,
    ]);
    $tenant->domains()->create(['domain' => 'price-'.$suffix.'.example.test']);
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
 * Seed a product (category -> collection -> colour -> product) and return the
 * created Product model plus its collection id.
 *
 * @return array{product: Product, collection_id: int}
 */
function seedPricingProduct(string $sku = 'SKU-PRICE'): array
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

    $product = Product::create([
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'collection_id' => $collectionId,
        'colour_id' => $colourId,
        'tax_type' => 'gst_18',
        'uom' => 'pcs',
    ]);

    return ['product' => $product, 'collection_id' => $collectionId];
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownPricingTenants(array $registry): void
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

it('prefers a product-level price over the collection-level price (R12.1, R12.4)', function () {
    $registry = [];
    $tenant = provisionPricingTenant($registry);

    try {
        $tenant->run(function () {
            ['product' => $product, 'collection_id' => $collectionId] = seedPricingProduct('SKU-OVERRIDE');

            Price::create(['level' => Price::LEVEL_COLLECTION, 'ref_id' => $collectionId, 'amount' => 200.0]);
            Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => 150.0]);

            $resolved = (new PriceResolutionService())->resolveForProduct($product);

            // Product-level price wins over the collection-level price.
            expect($resolved->base)->toBe(150.0);
            expect($resolved->final)->toBe(150.0);
        });
    } finally {
        teardownPricingTenants($registry);
    }
});

it('falls back to the collection-level price and applies its markup (R12.1, R12.2)', function () {
    $registry = [];
    $tenant = provisionPricingTenant($registry);

    try {
        $tenant->run(function () {
            ['product' => $product, 'collection_id' => $collectionId] = seedPricingProduct('SKU-FALLBACK');

            // No product-level price -> collection price with a 20% markup.
            Price::create([
                'level' => Price::LEVEL_COLLECTION,
                'ref_id' => $collectionId,
                'amount' => 100.0,
                'markup_type' => Price::MARKUP_TYPE_MARKUP,
                'markup_value' => 20.0,
            ]);

            $resolved = (new PriceResolutionService())->resolveForProduct($product);

            expect($resolved->base)->toBe(100.0);
            expect(round($resolved->markedUp, 2))->toBe(120.0);
            expect(round($resolved->final, 2))->toBe(120.0);
        });
    } finally {
        teardownPricingTenants($registry);
    }
});

it('applies online + buyer-scoped customer discounts cumulatively, never negative (R12.5, R12.4)', function () {
    $registry = [];
    $tenant = provisionPricingTenant($registry);

    try {
        $tenant->run(function () {
            ['product' => $product, 'collection_id' => $collectionId] = seedPricingProduct('SKU-DISCOUNT');

            Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => 100.0]);

            // Broadly-applicable online discount (no buyer scope).
            Discount::create(['type' => Discount::TYPE_ONLINE, 'percentage' => 10.0]);
            // Customer discount scoped to buyer (dealer, id 7).
            Discount::create([
                'type' => Discount::TYPE_CUSTOMER,
                'percentage' => 20.0,
                'buyer_type' => 'dealer',
                'buyer_id' => 7,
            ]);
            // A customer discount for a DIFFERENT buyer must NOT apply.
            Discount::create([
                'type' => Discount::TYPE_CUSTOMER,
                'percentage' => 50.0,
                'buyer_type' => 'dealer',
                'buyer_id' => 99,
            ]);

            $resolved = (new PriceResolutionService())->resolveForProduct($product, 'dealer', 7);

            // 100 * 0.9 (online) * 0.8 (customer for buyer 7) = 72; buyer 99's
            // 50% discount is excluded.
            expect(round($resolved->final, 2))->toBe(72.0);
            expect($resolved->final)->toBeGreaterThanOrEqual(0.0);
            expect($resolved->currency)->toBe('EUR');
        });
    } finally {
        teardownPricingTenants($registry);
    }
});

it('rejects a discount type other than customer/online at the model layer (R12.3)', function () {
    expect(fn () => new Discount(['type' => 'seasonal', 'percentage' => 5.0]))
        ->toThrow(InvalidArgumentException::class);
});
