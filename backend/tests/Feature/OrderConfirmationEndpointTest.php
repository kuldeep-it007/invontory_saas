<?php

declare(strict_types=1);

use App\Exceptions\IllegalOrderTransitionException;
use App\Http\Controllers\OrderConfirmationController;
use App\Mail\OrderConfirmedMail;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Stock;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Task 9.20 — Order confirmation endpoint (R16, R21.3, R21.5, R27.3)
|--------------------------------------------------------------------------
|
| Exercises the thin OrderConfirmationController, which delegates to
| OrderConfirmationService, against a real provisioned tenant database:
|   • a legal confirm returns the confirmed order + its invoice and queues the
|     order-confirmed email (R21.3, R21.5, R27.3);
|   • an illegal confirm bubbles the service's 422 and changes nothing (R21.4).
|
| The route wiring + RBAC gates (permission:orders.confirm + subscription.active)
| mirror the other seller-side order mutations and are asserted in the routing
| layer; this suite proves the controller→service delegation and response shape.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the ordering suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for confirm-endpoint tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionConfirmEndpointTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cfend'.$suffix,
        'subdomain' => 'cfend-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'cfend-'.$suffix.'.example.test']);
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
function seedConfirmEndpointProduct(string $sku = 'SKU-CE'): int
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
function teardownConfirmEndpointTenants(array $registry): void
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

it('confirm endpoint returns the confirmed order with its invoice and queues the email (R21.3, R21.5, R27.3)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionConfirmEndpointTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Dist One',
                'contact_email' => 'dist@example.test',
            ]);
            $productId = seedConfirmEndpointProduct('SKU-CE-OK');

            (new StockMovementService)->apply([
                StockMovement::forOwner($company, $productId, $companyPoint->id, '100.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '250.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '10.000',
                'unit_price' => '25.00',
                'discount' => '0.00',
            ]);

            $controller = app(OrderConfirmationController::class);
            $response = $controller->store(Request::create('/api/orders/'.$order->getKey().'/confirm', 'POST'), $order);

            expect($response->getStatusCode())->toBe(200);

            $payload = $response->getData(true)['data'];

            // The confirmed order is returned with its new status (R21.3).
            expect($payload['order']['status'])->toBe(OrderStatus::CONFIRMED);
            expect($order->fresh()->status)->toBe(OrderStatus::CONFIRMED);

            // The invoice issued under the seller's own series is returned (R21.5).
            expect($payload['invoice'])->not->toBeNull();
            expect($payload['invoice']['order_id'])->toBe((int) $order->getKey());
            expect($payload['invoice']['series'])->toBe($company->getMorphClass().'-'.$company->getKey());
            expect(Invoice::count())->toBe(1);

            // Stock moved: company decremented from 100 to 90 (R16.1).
            $companyQty = Stock::where('stockable_type', $company->getMorphClass())
                ->where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity;
            expect((string) $companyQty)->toBe('90.000');
        });

        // Order-confirmed email queued after commit (R27.3).
        Mail::assertQueued(OrderConfirmedMail::class);
    } finally {
        teardownConfirmEndpointTenants($registry);
    }
});

it('confirm endpoint bubbles the service 422 on an illegal confirm and changes nothing (R21.4)', function () {
    $registry = [];
    $tenant = provisionConfirmEndpointTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);

            // Already Delivered — `confirm` is undefined from here.
            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::DELIVERED,
                'order_type' => 'standard',
                'total' => '10.00',
            ]);

            $controller = app(OrderConfirmationController::class);

            $threw = null;
            try {
                $controller->store(Request::create('/api/orders/'.$order->getKey().'/confirm', 'POST'), $order);
            } catch (IllegalOrderTransitionException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
            expect($order->fresh()->status)->toBe(OrderStatus::DELIVERED);
            expect(Invoice::count())->toBe(0);
        });
    } finally {
        teardownConfirmEndpointTenants($registry);
    }
});
