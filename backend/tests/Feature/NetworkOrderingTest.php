<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Price;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\Tenant;
use App\Services\CartService;
use App\Services\DistributorService;
use App\Services\OrderConfirmationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Network ordering: distributor-to-distributor (phase 3)
|--------------------------------------------------------------------------
|
| A cart line is sold by the holder of the stock point the buyer picks. A cart
| mixing Company stock and another distributor's stock becomes one order per
| seller, and confirming a distributor-to-distributor order moves the stock
| from the selling distributor to the buying one.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * @param  array<int, Tenant>  $registry
 */
function provisionNetworkTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create(['id' => 'net'.$suffix, 'subdomain' => 'net-'.$suffix, 'currency' => 'INR']);
    $tenant->domains()->create(['domain' => 'net-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);
        Company::create(['name' => 'Network Co']);
    });

    return $tenant;
}

function networkProduct(string $sku, float $price): Product
{
    $now = now();
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Cat '.$sku, 'code' => 'C-'.$sku, 'created_at' => $now, 'updated_at' => $now]);
    $collectionId = DB::table('collections')->insertGetId(['category_id' => $categoryId, 'name' => 'Col '.$sku, 'code' => 'L-'.$sku, 'created_at' => $now, 'updated_at' => $now]);
    $colourId = DB::table('colours')->insertGetId(['collection_id' => $collectionId, 'name' => 'Clr '.$sku, 'code' => 'R-'.$sku, 'created_at' => $now, 'updated_at' => $now]);

    $product = Product::create([
        'sku' => $sku, 'hsn_code' => '6907', 'name' => 'Product '.$sku,
        'collection_id' => $collectionId, 'colour_id' => $colourId, 'tax_type' => 'gst_18', 'uom' => 'pcs',
    ]);
    Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => $price]);

    return $product;
}

function networkStock(Model $holder, Product $product, float $quantity, string $pointName): StockPoint
{
    $point = new StockPoint(['name' => $pointName, 'sort_order' => 0]);
    $point->owner()->associate($holder);
    $point->save();

    Stock::query()->forceCreate([
        'stockable_type' => $holder->getMorphClass(),
        'stockable_id' => $holder->getKey(),
        'product_id' => $product->id,
        'stock_point_id' => $point->id,
        'quantity' => $quantity,
    ]);

    return $point;
}

function networkOnHand(Model $holder, Product $product): float
{
    return (float) Stock::query()
        ->where('stockable_type', $holder->getMorphClass())
        ->where('stockable_id', $holder->getKey())
        ->where('product_id', $product->id)
        ->sum('quantity');
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownNetworkTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

it('splits a cart into one order per seller and moves stock between distributors on confirmation', function () {
    $registry = [];
    $tenant = provisionNetworkTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::query()->firstOrFail();
            $north = app(DistributorService::class)->create(['name' => 'North']);
            $south = app(DistributorService::class)->create(['name' => 'South']);

            $tiles = networkProduct('TILE', 100.0);
            $grout = networkProduct('GROUT', 10.0);
            networkStock($company, $tiles, 50, 'Main');
            networkStock($company, $grout, 50, 'Main grout');
            $southPoint = networkStock($south, $tiles, 8, 'South depot');

            $cart = app(CartService::class);
            // Tiles from South's depot (distributor to distributor), grout from the Company.
            $cart->addItem($north, $tiles->id, 5, [['stock_point_id' => $southPoint->id, 'quantity' => 5]]);
            $cart->addItem($north, $grout->id, 2);

            $orders = $cart->submitAll($north);

            expect($orders)->toHaveCount(2);
            $bySeller = collect($orders)->keyBy(fn (Order $o) => $o->seller_type.'#'.$o->seller_id);
            $d2d = $bySeller[$south->getMorphClass().'#'.$south->id];
            $fromCompany = $bySeller[$company->getMorphClass().'#'.$company->id];

            expect($d2d->lines)->toHaveCount(1)
                ->and((int) $d2d->lines[0]->product_id)->toBe($tiles->id)
                ->and((float) $d2d->total)->toBe(500.0)
                ->and((int) $fromCompany->lines[0]->product_id)->toBe($grout->id);

            app(OrderConfirmationService::class)->confirm($d2d->fresh());

            expect(networkOnHand($south, $tiles))->toBe(3.0)
                ->and(networkOnHand($north, $tiles))->toBe(5.0)
                ->and(networkOnHand($company, $tiles))->toBe(50.0);
        });
    } finally {
        teardownNetworkTenants($registry);
    }
});

it('blocks ordering more than the chosen distributor holds', function () {
    $registry = [];
    $tenant = provisionNetworkTenant($registry);

    try {
        $tenant->run(function () {
            $north = app(DistributorService::class)->create(['name' => 'North']);
            $south = app(DistributorService::class)->create(['name' => 'South']);
            $tiles = networkProduct('TILE', 100.0);
            $southPoint = networkStock($south, $tiles, 8, 'South depot');

            expect(fn () => app(CartService::class)->addItem($north, $tiles->id, 9, [['stock_point_id' => $southPoint->id, 'quantity' => 9]]))
                ->toThrow(UnprocessableEntityHttpException::class);
        });
    } finally {
        teardownNetworkTenants($registry);
    }
});

it('sells a distributor-owned product from its owner by default', function () {
    $registry = [];
    $tenant = provisionNetworkTenant($registry);

    try {
        $tenant->run(function () {
            $north = app(DistributorService::class)->create(['name' => 'North']);
            $south = app(DistributorService::class)->create(['name' => 'South']);
            $own = networkProduct('SOUTH-OWN', 40.0);
            $own->owner_distributor_id = $south->id;
            $own->save();
            networkStock($south, $own, 10, 'South depot');

            $cart = app(CartService::class);
            $cart->addItem($north, $own->id, 2);
            $order = $cart->submit($north);

            expect($order->seller_type)->toBe((new Distributor)->getMorphClass())
                ->and((int) $order->seller_id)->toBe($south->id);
        });
    } finally {
        teardownNetworkTenants($registry);
    }
});
