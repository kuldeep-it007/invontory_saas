<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Price;
use App\Models\Product;
use App\Models\Tenant;
use App\Mail\OrderPlacedMail;
use App\Services\CartSubmissionService;
use App\Services\DistributorService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Task 9.1 — Cart → Order submission (R20.1–R20.4)
|--------------------------------------------------------------------------
|
| Exercises CartSubmissionService against a real provisioned tenant database:
|   • submitting a cart of N entries creates EXACTLY ONE order with one line
|     per entry — cart-to-order fidelity (R20.3, R20.4);
|   • a Distributor order is placed against the single tenant Company
|     (seller = Company — R20.1);
|   • a Dealer order is placed against its serving Distributor
|     (seller = Distributor — R20.2);
|   • an empty cart is rejected (R20.3 — a cart must have at least one line).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the catalog/stock/ordering suites.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * @param  array<int, Tenant>  $registry
 */
function provisionCartTenant(array &$registry, string $currency = 'USD'): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cart'.$suffix,
        'subdomain' => 'cart-'.$suffix,
        'currency' => $currency,
    ]);
    $tenant->domains()->create(['domain' => 'cart-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        // The tenant's single Company (seller for distributor orders — R20.1).
        Company::create(['name' => 'Cart Co']);
    });

    return $tenant;
}

/**
 * Seed a priced product and return it.
 */
function seedCartProduct(string $sku, float $price): Product
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

    Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => $price]);

    return $product;
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownCartTenants(array $registry): void
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

it('creates exactly one order with one line per cart entry for a Distributor against the Company (R20.1, R20.3, R20.4)', function () {
    $registry = [];
    $tenant = provisionCartTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::query()->firstOrFail();
            $distributor = app(DistributorService::class)->create(['name' => 'Buyer Distributor']);

            $p1 = seedCartProduct('SKU-A', 100.0);
            $p2 = seedCartProduct('SKU-B', 50.0);
            $p3 = seedCartProduct('SKU-C', 25.0);

            $order = app(CartSubmissionService::class)->submit($distributor, [
                ['product_id' => $p1->id, 'quantity' => '2'],
                ['product_id' => $p2->id, 'quantity' => '3'],
                ['product_id' => $p3->id, 'quantity' => '1'],
            ]);

            // Exactly one order, with one line per cart entry (R20.4).
            expect(Order::query()->count())->toBe(1);
            expect(OrderLine::query()->where('order_id', $order->getKey())->count())->toBe(3);
            expect($order->lines)->toHaveCount(3);

            // The order is Placed and typed standard (R21.1).
            expect($order->status)->toBe(OrderStatus::PLACED);
            expect($order->order_type)->toBe('standard');

            // Buyer = the Distributor; Seller = the single tenant Company (R20.1).
            expect($order->buyer_type)->toBe($distributor->getMorphClass());
            expect($order->buyer_id)->toBe($distributor->getKey());
            expect($order->seller_type)->toBe($company->getMorphClass());
            expect($order->seller_id)->toBe($company->getKey());

            // Each line records the ordered product + quantity.
            $byProduct = $order->lines->keyBy('product_id');
            expect((float) $byProduct[$p1->id]->quantity)->toBe(2.0);
            expect((float) $byProduct[$p2->id]->quantity)->toBe(3.0);
            expect((float) $byProduct[$p3->id]->quantity)->toBe(1.0);

            // Total = 2*100 + 3*50 + 1*25 = 375 (R39.1, single currency).
            expect((float) $order->total)->toBe(375.0);
        });
    } finally {
        teardownCartTenants($registry);
    }
});

it('places a Dealer order against the serving Distributor as seller (R20.2)', function () {
    $registry = [];
    $tenant = provisionCartTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create(['name' => 'Serving Distributor']);
            $dealer = Dealer::create([
                'distributor_id' => $distributor->getKey(),
                'name' => 'Buyer Dealer',
            ]);

            $product = seedCartProduct('SKU-D', 40.0);

            $order = app(CartSubmissionService::class)->submit($dealer, [
                ['product_id' => $product->id, 'quantity' => '5'],
            ]);

            expect(Order::query()->count())->toBe(1);
            expect($order->lines)->toHaveCount(1);

            // Buyer = the Dealer; Seller = its serving Distributor (R20.2).
            expect($order->buyer_type)->toBe($dealer->getMorphClass());
            expect($order->buyer_id)->toBe($dealer->getKey());
            expect($order->seller_type)->toBe($distributor->getMorphClass());
            expect($order->seller_id)->toBe($distributor->getKey());

            expect((float) $order->total)->toBe(200.0);
        });
    } finally {
        teardownCartTenants($registry);
    }
});

it('queues an order-placed email to the buyer after submission (R27.2)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionCartTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create([
                'name' => 'Notified Distributor',
                'contact_email' => 'buyer@example.test',
            ]);

            $product = seedCartProduct('SKU-NOTIFY', 20.0);

            app(CartSubmissionService::class)->submit($distributor, [
                ['product_id' => $product->id, 'quantity' => '2'],
            ]);
        });

        // Order-placed email queued to the buyer after commit (R27.2).
        Mail::assertQueued(OrderPlacedMail::class);
    } finally {
        teardownCartTenants($registry);
    }
});

it('does not queue an order-placed email when the buyer has no contact email (R27.2)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionCartTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create(['name' => 'No Email Distributor']);
            $product = seedCartProduct('SKU-NOEMAIL', 20.0);

            app(CartSubmissionService::class)->submit($distributor, [
                ['product_id' => $product->id, 'quantity' => '1'],
            ]);
        });

        Mail::assertNothingQueued();
    } finally {
        teardownCartTenants($registry);
    }
});

it('rejects submitting an empty cart (R20.3)', function () {
    $registry = [];
    $tenant = provisionCartTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create(['name' => 'Empty Cart Distributor']);

            expect(fn () => app(CartSubmissionService::class)->submit($distributor, []))
                ->toThrow(InvalidArgumentException::class);

            expect(Order::query()->count())->toBe(0);
        });
    } finally {
        teardownCartTenants($registry);
    }
});
