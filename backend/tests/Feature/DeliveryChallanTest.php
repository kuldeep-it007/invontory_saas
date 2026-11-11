<?php

declare(strict_types=1);

use App\Http\Controllers\DeliveryChallanController;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Services\DeliveryChallanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Task 15.2 — Delivery-challan printing (R33.4, R28.7)
|--------------------------------------------------------------------------
|
| Exercises DeliveryChallanService + the thin DeliveryChallanController against
| a real provisioned tenant database:
|   • a dispatched order renders a structured challan whose reference is the
|     invoice number, whose lines mirror the order lines, and whose per-line
|     totals = qty×price − discount clamped at zero (R33.4, R28.7, R39.1);
|   • `?format=pdf` streams a genuine application/pdf download (R33.4);
|   • a not-yet-dispatched order is rejected with a 422, printing nothing (the
|     challan is a dispatch/delivery document — R21.1).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the ordering suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for challan tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionChallanTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'chln'.$suffix,
        'subdomain' => 'chln-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'chln-'.$suffix.'.example.test']);
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
function seedChallanProduct(string $sku = 'SKU-CH'): int
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
 * Seed a dispatched order with one invoice and one line, returning the order.
 */
function seedDispatchedOrder(string $status = OrderStatus::DISPATCHED): Order
{
    $company = Company::create(['name' => 'Acme Mills']);
    $distributor = Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'North Distributor',
        'contact_email' => 'dist@example.test',
    ]);
    $productId = seedChallanProduct('SKU-CH-'.strtoupper(bin2hex(random_bytes(2))));

    $order = Order::create([
        'buyer_type' => $distributor->getMorphClass(),
        'buyer_id' => $distributor->getKey(),
        'seller_type' => $company->getMorphClass(),
        'seller_id' => $company->getKey(),
        'status' => $status,
        'order_type' => 'standard',
        'total' => '240.00',
    ]);
    OrderLine::create([
        'order_id' => $order->getKey(),
        'product_id' => $productId,
        'quantity' => '10.000',
        'unit_price' => '25.00',
        'discount' => '10.00',
    ]);

    $invoice = new Invoice([
        'order_id' => $order->getKey(),
        'series' => $company->getMorphClass().'-'.$company->getKey(),
        'number' => 1,
        'amount' => '240.00',
        'overdue' => false,
    ]);
    $invoice->seller()->associate($company);
    $invoice->save();

    return $order->fresh();
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownChallanTenants(array $registry): void
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

it('builds a structured challan for a dispatched order with invoice reference, parties, and line totals (R33.4, R28.7)', function () {
    $registry = [];
    $tenant = provisionChallanTenant($registry);

    try {
        $tenant->run(function () {
            $order = seedDispatchedOrder();

            $challan = app(DeliveryChallanService::class)->build($order);

            // Reference is the invoice series/number (R28.7).
            expect($challan['reference'])->toContain('-'.$order->seller_id.'/1');

            // Parties resolve by display name (R33.4).
            expect($challan['seller']['name'])->toBe('Acme Mills');
            expect($challan['buyer']['name'])->toBe('North Distributor');

            // One line mirroring the order line (R28.7).
            expect($challan['line_count'])->toBe(1);
            expect($challan['total_quantity'])->toBe('10.000');

            $line = $challan['lines'][0];
            expect($line['quantity'])->toBe('10.000');
            expect($line['unit_price'])->toBe('25.00');
            expect($line['discount'])->toBe('10.00');
            // qty×price − discount = 250.00 − 10.00 = 240.00 (R39.1).
            expect($line['line_total'])->toBe('240.00');
            expect($line['sku'])->toStartWith('SKU-CH-');
        });
    } finally {
        teardownChallanTenants($registry);
    }
});

it('line totals never go negative when the discount exceeds the gross (R39.1)', function () {
    $registry = [];
    $tenant = provisionChallanTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme Mills']);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'North']);
            $productId = seedChallanProduct('SKU-CH-NEG');

            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::DELIVERED,
                'order_type' => 'standard',
                'total' => '0.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '2.000',
                'unit_price' => '5.00',
                // Discount larger than gross (10.00) would drive the line below zero.
                'discount' => '50.00',
            ]);

            $challan = app(DeliveryChallanService::class)->build($order->fresh());

            expect($challan['lines'][0]['line_total'])->toBe('0.00');
        });
    } finally {
        teardownChallanTenants($registry);
    }
});

it('rejects printing a challan for an order that has not been dispatched (R21.1)', function () {
    $registry = [];
    $tenant = provisionChallanTenant($registry);

    try {
        $tenant->run(function () {
            $order = seedDispatchedOrder(OrderStatus::CONFIRMED);

            $threw = null;
            try {
                app(DeliveryChallanService::class)->build($order);
            } catch (UnprocessableEntityHttpException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
        });
    } finally {
        teardownChallanTenants($registry);
    }
});

it('controller returns the structured challan JSON by default (R33.4)', function () {
    $registry = [];
    $tenant = provisionChallanTenant($registry);

    try {
        $tenant->run(function () {
            $order = seedDispatchedOrder();

            $controller = app(DeliveryChallanController::class);
            $response = $controller->show(
                Request::create('/api/orders/'.$order->getKey().'/challan', 'GET'),
                $order,
            );

            expect($response->getStatusCode())->toBe(200);

            $payload = $response->getData(true)['data'];
            expect($payload['order']['id'])->toBe((int) $order->getKey());
            expect($payload['order']['status'])->toBe(OrderStatus::DISPATCHED);
            expect($payload['line_count'])->toBe(1);
        });
    } finally {
        teardownChallanTenants($registry);
    }
});

it('controller streams a printable PDF download when format=pdf (R33.4)', function () {
    $registry = [];
    $tenant = provisionChallanTenant($registry);

    try {
        $tenant->run(function () {
            $order = seedDispatchedOrder();

            $controller = app(DeliveryChallanController::class);
            $response = $controller->show(
                Request::create('/api/orders/'.$order->getKey().'/challan', 'GET', ['format' => 'pdf']),
                $order,
            );

            expect($response)->toBeInstanceOf(BinaryFileResponse::class);
            expect($response->headers->get('Content-Type'))->toContain('application/pdf');

            // The rendered file begins with the PDF magic header.
            $path = $response->getFile()->getPathname();
            expect(is_file($path))->toBeTrue();
            expect(substr((string) file_get_contents($path), 0, 5))->toBe('%PDF-');
        });
    } finally {
        teardownChallanTenants($registry);
    }
});
