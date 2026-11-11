<?php

declare(strict_types=1);

use App\Exceptions\FeatureDisabledException;
use App\Exceptions\InsufficientAvailableStockException;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\AdvancedOrderService;
use App\Services\StockReservationService;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Task 15.6 — Stock reservations & advanced order types (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the reservation availability invariant and the advanced-order
| placement flow against a real provisioned tenant database:
|   • available = on-hand − Σ(active reservations), without moving owned
|     quantity (R23.4);
|   • a reservation that would exceed available is rejected with a 422 (R23.4);
|   • reserve then release/expire restores available to its prior value (R23.5);
|   • blanket orders carry scheduled-release reservations (R23.1); advance
|     orders are gated by the `advance_orders` flag (R23.2); depot orders
|     reserve at the stock-point level (R23.3).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock-movement suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * @param  array<int, Tenant>  $registry
 */
function provisionReservationTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'resv'.$suffix,
        'subdomain' => 'resv-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'resv-'.$suffix.'.example.test']);
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
 * Attach a central plan + active subscription to a tenant, optionally enabling
 * the `advance_orders` feature flag, so the subscription gate resolves (R23.2).
 */
function attachAdvanceOrdersPlan(Tenant $tenant, bool $advanceOrders): void
{
    $plan = Plan::create([
        'name' => 'Test '.$tenant->id,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    $plan->features()->create([
        'key' => PlanFeature::ADVANCE_ORDERS,
        'enabled' => $advanceOrders,
    ]);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_ACTIVE,
        'starts_at' => now(),
    ]);
}

function seedReservationProduct(string $sku = 'SKU-R'): int
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
 * Seed one Company-owned stock row at the given quantity and return it.
 */
function seedCompanyStock(Company $company, int $productId, string $quantity, ?int $stockPointId = null): Stock
{
    $pointId = $stockPointId ?? $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0])->id;

    $stock = new Stock;
    $stock->forceFill([
        'stockable_type' => $company->getMorphClass(),
        'stockable_id' => $company->getKey(),
        'product_id' => $productId,
        'stock_point_id' => $pointId,
        'quantity' => $quantity,
    ])->save();

    return $stock->refresh();
}

/**
 * Seed a minimal placed Order owned by the Company (buyer = seller = company
 * is irrelevant here; the reservation only needs a valid order_id to satisfy
 * the FK).
 */
function seedReservationOrder(Company $company): Order
{
    $order = new Order([
        'status' => OrderStatus::PLACED,
        'order_type' => Order::TYPE_STANDARD,
        'total' => '0',
    ]);
    $order->buyer()->associate($company);
    $order->seller()->associate($company);
    $order->save();

    return $order;
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownReservationTenants(array $registry): void
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

it('migrates stock_reservations without a tenant_id discriminator', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);

    try {
        $tenant->run(function () {
            expect(Schema::hasTable('stock_reservations'))->toBeTrue();
            expect(Schema::hasColumns('stock_reservations', [
                'id', 'stock_id', 'order_id', 'quantity', 'status', 'expires_at',
            ]))->toBeTrue();
            expect(Schema::hasColumn('stock_reservations', 'tenant_id'))->toBeFalse();
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('computes available as on-hand minus active reservations without moving owned quantity', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $productId = seedReservationProduct('SKU-AVAIL');
            $stock = seedCompanyStock($company, $productId, '100.000');

            $service = new StockReservationService;

            expect($service->availableQuantity($stock))->toBe('100.000');

            $service->reserve($stock, '30.000');
            $service->reserve($stock, '20.500');

            // On-hand is UNCHANGED — a reservation only blocks (R23.4).
            expect((string) $stock->refresh()->quantity)->toBe('100.000');
            // Available dropped by the active reservations.
            expect($service->reservedQuantity((int) $stock->getKey()))->toBe('50.500');
            expect($service->availableQuantity($stock->refresh()))->toBe('49.500');
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('rejects a reservation that would exceed available and persists nothing', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $productId = seedReservationProduct('SKU-OVER');
            $stock = seedCompanyStock($company, $productId, '10.000');

            $service = new StockReservationService;
            $service->reserve($stock, '7.000');

            $threw = null;
            try {
                $service->reserve($stock, '5.000'); // only 3 available
            } catch (InsufficientAvailableStockException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
            expect($threw->available)->toBe('3.000');

            // All-or-nothing: the rejected reservation did not persist (R23.4).
            expect(StockReservation::query()->active()->count())->toBe(1);
            expect($service->availableQuantity($stock->refresh()))->toBe('3.000');
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('restores available after releasing a reservation (round-trip)', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $productId = seedReservationProduct('SKU-RT');
            $stock = seedCompanyStock($company, $productId, '40.000');

            $service = new StockReservationService;

            $before = $service->availableQuantity($stock);
            $reservation = $service->reserve($stock, '25.000');
            expect($service->availableQuantity($stock->refresh()))->toBe('15.000');

            $service->release($reservation);

            // Releasing returns the blocked quantity to available (R23.5).
            expect($service->availableQuantity($stock->refresh()))->toBe($before);
            expect($reservation->refresh()->status)->toBe(StockReservation::STATUS_RELEASED);
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('releases every active reservation held for an order', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $productId = seedReservationProduct('SKU-ORD');
            $stock = seedCompanyStock($company, $productId, '50.000');

            $orderA = seedReservationOrder($company);
            $orderB = seedReservationOrder($company);

            $service = new StockReservationService;
            $service->reserve($stock, '10.000', $orderA->id);
            $service->reserve($stock, '5.000', $orderA->id);
            $service->reserve($stock, '3.000', $orderB->id); // another order

            $released = $service->releaseForOrder($orderA->id);

            expect($released)->toBe(2);
            // Only order B's 3.000 still blocks → available = 50 − 3 = 47.
            expect($service->availableQuantity($stock->refresh()))->toBe('47.000');
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('releases expired reservations back to available (scheduled lapse)', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $productId = seedReservationProduct('SKU-EXP');
            $stock = seedCompanyStock($company, $productId, '20.000');

            $service = new StockReservationService;
            // One already-expired reservation and one open-ended one.
            $service->reserve($stock, '8.000', null, ['expires_at' => now()->subMinute()]);
            $service->reserve($stock, '4.000');

            expect($service->availableQuantity($stock->refresh()))->toBe('8.000');

            $released = $service->releaseExpired();

            expect($released)->toBe(1);
            // The expired 8.000 is freed; the open 4.000 still blocks.
            expect($service->availableQuantity($stock->refresh()))->toBe('16.000');
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('places a depot order reserving at the named stock point (R23.3)', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);
    attachAdvanceOrdersPlan($tenant, advanceOrders: true);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create(['name' => 'Dist', 'company_id' => $company->id]);

            $productId = seedReservationProduct('SKU-DEPOT');
            $mainPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $depotPoint = $company->stockPoints()->create(['name' => 'Depot', 'sort_order' => 1]);

            seedCompanyStock($company, $productId, '12.000', $mainPoint->id);
            $depotStock = seedCompanyStock($company, $productId, '30.000', $depotPoint->id);

            $service = new AdvancedOrderService(new StockReservationService, new SubscriptionGateService);

            $order = $service->place($distributor, Order::TYPE_DEPOT, [
                ['product_id' => $productId, 'quantity' => '18.000', 'stock_point_id' => $depotPoint->id],
            ]);

            expect($order->order_type)->toBe(Order::TYPE_DEPOT);
            expect($order->status)->toBe(OrderStatus::PLACED);

            $resv = new StockReservationService;
            // The DEPOT stock row is reserved, not the primary point (R23.3).
            expect($resv->availableQuantity($depotStock->refresh()))->toBe('12.000');
            expect(StockReservation::query()->where('stock_id', $depotStock->id)->active()->count())->toBe(1);
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('denies an advance order when the advance_orders flag is off (R23.2)', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);
    attachAdvanceOrdersPlan($tenant, advanceOrders: false);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create(['name' => 'Dist', 'company_id' => $company->id]);
            $productId = seedReservationProduct('SKU-ADV-OFF');
            seedCompanyStock($company, $productId, '50.000');

            $service = new AdvancedOrderService(new StockReservationService, new SubscriptionGateService);

            // Inside $tenant->run tenancy is initialized, so the subscription
            // gate resolves tenant('id') and reads the (disabled) advance_orders
            // flag from the central plan (R23.2).
            $threw = null;
            try {
                $service->place($distributor, Order::TYPE_ADVANCE, [
                    ['product_id' => $productId, 'quantity' => '5.000'],
                ]);
            } catch (FeatureDisabledException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(403);
            expect($threw->feature)->toBe(PlanFeature::ADVANCE_ORDERS);
            // Nothing was placed/reserved — the gate denies before any write.
            expect(Order::query()->count())->toBe(0);
            expect(StockReservation::query()->count())->toBe(0);
        });
    } finally {
        teardownReservationTenants($registry);
    }
});

it('places an advance order reserving stock when the flag is on (R23.2, R23.4)', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);
    attachAdvanceOrdersPlan($tenant, advanceOrders: true);

    try {
        tenancy()->initialize($tenant);
        try {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create(['name' => 'Dist', 'company_id' => $company->id]);
            $productId = seedReservationProduct('SKU-ADV-ON');
            $stock = seedCompanyStock($company, $productId, '50.000');

            $service = new AdvancedOrderService(new StockReservationService, new SubscriptionGateService);

            $order = $service->place($distributor, Order::TYPE_ADVANCE, [
                ['product_id' => $productId, 'quantity' => '15.000'],
            ]);

            expect($order->order_type)->toBe(Order::TYPE_ADVANCE);

            $resv = new StockReservationService;
            expect((string) $stock->refresh()->quantity)->toBe('50.000'); // on-hand untouched
            expect($resv->availableQuantity($stock->refresh()))->toBe('35.000');
        } finally {
            tenancy()->end();
        }
    } finally {
        teardownReservationTenants($registry);
    }
});

it('places a blanket order carrying a scheduled-release reservation (R23.1)', function () {
    $registry = [];
    $tenant = provisionReservationTenant($registry);
    attachAdvanceOrdersPlan($tenant, advanceOrders: true);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create(['name' => 'Dist', 'company_id' => $company->id]);
            $productId = seedReservationProduct('SKU-BLANKET');
            $stock = seedCompanyStock($company, $productId, '100.000');

            $service = new AdvancedOrderService(new StockReservationService, new SubscriptionGateService);

            $releaseAt = now()->addDays(7);
            $order = $service->place($distributor, Order::TYPE_BLANKET, [
                ['product_id' => $productId, 'quantity' => '40.000', 'expires_at' => $releaseAt],
            ]);

            expect($order->order_type)->toBe(Order::TYPE_BLANKET);

            $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
            expect($reservation->status)->toBe(StockReservation::STATUS_ACTIVE);
            expect($reservation->expires_at)->not->toBeNull();
            expect($reservation->expires_at->toDateString())->toBe($releaseAt->toDateString());
        });
    } finally {
        teardownReservationTenants($registry);
    }
});
