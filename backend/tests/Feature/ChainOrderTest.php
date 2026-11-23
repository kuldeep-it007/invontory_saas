<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Price;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\StockReservation;
use App\Models\Tenant;
use App\Services\CartService;
use App\Services\DistributorService;
use App\Services\OrderCancellationService;
use App\Services\OrderConfirmationService;
use App\Services\OrderListService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Chain orders (phase 4)
|--------------------------------------------------------------------------
|
| A dealer buying stock its distributor lacks creates Order 1 (SO-, dealer →
| distributor, every line) and Order 2 (PO-, distributor → holder, only the
| chained quantity), linked by a shared chain reference. Order 2's stock is
| reserved at the holder; Order 1 waits for Order 2 to be confirmed (to
| confirm) and delivered (to dispatch); cancelling Order 1 cancels Order 2.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * @param  array<int, Tenant>  $registry
 */
function chainTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create(['id' => 'chain'.$suffix, 'subdomain' => 'chain-'.$suffix, 'currency' => 'INR']);
    $tenant->domains()->create(['domain' => 'chain-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);
        Company::create(['name' => 'Chain Co']);
    });

    return $tenant;
}

function chainProduct(string $sku, float $price, ?int $ownerDistributorId = null): Product
{
    $now = now();
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Cat '.$sku, 'code' => 'C-'.$sku, 'owner_key' => $ownerDistributorId ?? 0, 'owner_distributor_id' => $ownerDistributorId, 'created_at' => $now, 'updated_at' => $now]);
    $collectionId = DB::table('collections')->insertGetId(['category_id' => $categoryId, 'name' => 'Col '.$sku, 'code' => 'L-'.$sku, 'owner_key' => $ownerDistributorId ?? 0, 'owner_distributor_id' => $ownerDistributorId, 'created_at' => $now, 'updated_at' => $now]);
    $colourId = DB::table('colours')->insertGetId(['collection_id' => $collectionId, 'name' => 'Clr '.$sku, 'code' => 'R-'.$sku, 'owner_key' => $ownerDistributorId ?? 0, 'owner_distributor_id' => $ownerDistributorId, 'created_at' => $now, 'updated_at' => $now]);

    $product = new Product([
        'sku' => $sku, 'hsn_code' => '6907', 'name' => 'Product '.$sku,
        'collection_id' => $collectionId, 'colour_id' => $colourId, 'tax_type' => 'gst_18', 'uom' => 'pcs',
    ]);
    $product->owner_distributor_id = $ownerDistributorId;
    $product->save();
    Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => $price]);

    return $product;
}

function chainPoint(Model $holder, string $name): StockPoint
{
    $point = new StockPoint(['name' => $name, 'sort_order' => 0]);
    $point->owner()->associate($holder);
    $point->save();

    return $point;
}

function chainStock(Model $holder, Product $product, StockPoint $point, float $quantity): void
{
    Stock::query()->forceCreate([
        'stockable_type' => $holder->getMorphClass(),
        'stockable_id' => $holder->getKey(),
        'product_id' => $product->id,
        'stock_point_id' => $point->id,
        'quantity' => $quantity,
    ]);
}

function chainOnHand(Model $holder, Product $product): float
{
    return (float) Stock::query()
        ->where('stockable_type', $holder->getMorphClass())
        ->where('stockable_id', $holder->getKey())
        ->where('product_id', $product->id)
        ->sum('quantity');
}

function chainActiveReserved(Order $order): float
{
    return (float) StockReservation::query()->where('order_id', $order->id)->where('status', StockReservation::STATUS_ACTIVE)->sum('quantity');
}

/**
 * @param  array<int, Tenant>  $registry
 */
function chainTeardown(array $registry): void
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

/**
 * Company + North (with a dealer) + South, a Company product (Company 50,
 * North 2, South 8) and a North-owned product (North 10).
 *
 * @return array<string, mixed>
 */
function chainNetwork(): array
{
    $company = Company::query()->firstOrFail();
    $north = app(DistributorService::class)->create(['name' => 'North']);
    $south = app(DistributorService::class)->create(['name' => 'South']);
    $dealer = Dealer::create(['distributor_id' => $north->id, 'name' => 'North Shop']);

    $tiles = chainProduct('TILE', 100.0);
    $own = chainProduct('NORTH-OWN', 40.0, $north->id);

    $companyPoint = chainPoint($company, 'Main');
    $northPoint = chainPoint($north, 'North depot');
    $southPoint = chainPoint($south, 'South depot');
    chainStock($company, $tiles, $companyPoint, 50);
    chainStock($north, $tiles, $northPoint, 2);
    chainStock($south, $tiles, $southPoint, 8);
    chainStock($north, $own, $northPoint, 10);

    return compact('company', 'north', 'south', 'dealer', 'tiles', 'own', 'companyPoint', 'northPoint', 'southPoint');
}

it('chains only the shortfall to the product owner and runs the chain lifecycle', function () {
    $registry = [];
    $tenant = chainTenant($registry);

    try {
        $tenant->run(function () {
            ['company' => $company, 'north' => $north, 'dealer' => $dealer, 'tiles' => $tiles] = chainNetwork();

            $cart = app(CartService::class);
            $cart->addItem($dealer, $tiles->id, 5);
            $orders = $cart->submitAll($dealer);

            expect($orders)->toHaveCount(1);
            $so = $orders[0];
            $po = Order::query()->where('parent_order_id', $so->id)->firstOrFail();

            // Order 1: dealer → North, all 5. Order 2: North → Company, the missing 3.
            expect($so->order_number)->toStartWith('SO-')
                ->and($po->order_number)->toStartWith('PO-')
                ->and($po->chain_ref)->toBe($so->chain_ref)->not->toBeNull()
                ->and($so->seller_id)->toBe($north->id)
                ->and($po->buyer_id)->toBe($north->id)
                ->and($po->seller_type)->toBe($company->getMorphClass())
                ->and((float) $so->lines[0]->quantity)->toBe(5.0)
                ->and((float) $po->lines()->first()->quantity)->toBe(3.0)
                ->and(chainActiveReserved($po))->toBe(3.0);

            $confirm = app(OrderConfirmationService::class);

            // Order 1 waits for Order 2.
            expect(fn () => $confirm->confirm($so->fresh()))->toThrow(UnprocessableEntityHttpException::class);

            $confirm->confirm($po->fresh());
            expect(chainOnHand($company, $tiles))->toBe(47.0)
                ->and(chainOnHand($north, $tiles))->toBe(5.0)
                ->and(chainActiveReserved($po))->toBe(0.0);

            $confirm->confirm($so->fresh());
            expect(chainOnHand($north, $tiles))->toBe(0.0);

            // Order 1 dispatches only after Order 2 is delivered.
            expect(fn () => $so->fresh()->transition('dispatch'))->toThrow(UnprocessableEntityHttpException::class);
            $po->fresh()->transition('dispatch');
            $po->fresh()->transition('deliver');
            expect($so->fresh()->transition('dispatch'))->toBe(OrderStatus::DISPATCHED);
        });
    } finally {
        chainTeardown($registry);
    }
});

it('chains the whole line to a holder the dealer picked', function () {
    $registry = [];
    $tenant = chainTenant($registry);

    try {
        $tenant->run(function () {
            ['north' => $north, 'south' => $south, 'dealer' => $dealer, 'tiles' => $tiles, 'southPoint' => $southPoint] = chainNetwork();

            $cart = app(CartService::class);
            $cart->addItem($dealer, $tiles->id, 4, [['stock_point_id' => $southPoint->id, 'quantity' => 4]]);
            $so = $cart->submit($dealer);
            $po = Order::query()->where('parent_order_id', $so->id)->firstOrFail();

            expect($so->seller_id)->toBe($north->id)
                ->and($po->seller_type)->toBe((new Distributor)->getMorphClass())
                ->and($po->seller_id)->toBe($south->id)
                ->and((float) $po->lines()->first()->quantity)->toBe(4.0)
                ->and(chainActiveReserved($po))->toBe(4.0);
        });
    } finally {
        chainTeardown($registry);
    }
});

it('does not chain what the distributor holds itself', function () {
    $registry = [];
    $tenant = chainTenant($registry);

    try {
        $tenant->run(function () {
            ['dealer' => $dealer, 'tiles' => $tiles] = chainNetwork();

            $cart = app(CartService::class);
            $cart->addItem($dealer, $tiles->id, 2);
            $so = $cart->submit($dealer);

            expect($so->chain_ref)->toBeNull()
                ->and(Order::query()->where('parent_order_id', $so->id)->count())->toBe(0);
        });
    } finally {
        chainTeardown($registry);
    }
});

it('cancels the upstream order with Order 1 and releases its reservation', function () {
    $registry = [];
    $tenant = chainTenant($registry);

    try {
        $tenant->run(function () {
            ['dealer' => $dealer, 'tiles' => $tiles] = chainNetwork();

            $cart = app(CartService::class);
            $cart->addItem($dealer, $tiles->id, 5);
            $so = $cart->submit($dealer);
            $po = Order::query()->where('parent_order_id', $so->id)->firstOrFail();

            $so->fresh()->transition('cancel');

            expect($po->fresh()->status)->toBe(OrderStatus::CANCELLED)
                ->and(chainActiveReserved($po))->toBe(0.0);
        });
    } finally {
        chainTeardown($registry);
    }
});

it('reverses a confirmed upstream order when Order 1 is cancelled', function () {
    $registry = [];
    $tenant = chainTenant($registry);

    try {
        $tenant->run(function () {
            ['company' => $company, 'north' => $north, 'dealer' => $dealer, 'tiles' => $tiles] = chainNetwork();

            $cart = app(CartService::class);
            $cart->addItem($dealer, $tiles->id, 5);
            $so = $cart->submit($dealer);
            $po = Order::query()->where('parent_order_id', $so->id)->firstOrFail();
            app(OrderConfirmationService::class)->confirm($po->fresh());

            app(OrderCancellationService::class)->cancel($so->fresh());

            expect($po->fresh()->status)->toBe(OrderStatus::CANCELLED)
                ->and(chainOnHand($company, $tiles))->toBe(50.0)
                ->and(chainOnHand($north, $tiles))->toBe(2.0);
        });
    } finally {
        chainTeardown($registry);
    }
});

it('shows the Company only the lines of its own products', function () {
    $registry = [];
    $tenant = chainTenant($registry);

    try {
        $tenant->run(function () {
            ['dealer' => $dealer, 'tiles' => $tiles, 'own' => $own] = chainNetwork();

            $cart = app(CartService::class);
            $cart->addItem($dealer, $tiles->id, 1);
            $cart->addItem($dealer, $own->id, 1);
            $so = $cart->submit($dealer);

            $companyView = collect(app(OrderListService::class)->all(15, ['company_lines_only' => true])->items())->firstWhere('id', $so->id);
            $fullView = collect(app(OrderListService::class)->all(15, [])->items())->firstWhere('id', $so->id);

            expect($fullView->lines)->toHaveCount(2)
                ->and($companyView->lines)->toHaveCount(1)
                ->and((int) $companyView->lines[0]->product_id)->toBe($tiles->id)
                ->and((int) $companyView->all_lines_count)->toBe(2);
        });
    } finally {
        chainTeardown($registry);
    }
});
