<?php

declare(strict_types=1);

use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\IllegalOrderTransitionException;
use App\Exceptions\InsufficientStockException;
use App\Mail\OrderConfirmedMail;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use App\Services\AccountLedgerService;
use App\Services\CreditEnforcementService;
use App\Services\InvoiceService;
use App\Services\OrderConfirmationService;
use App\Services\OrderStateMachine;
use App\Services\StockMovementService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Task 9.7 — OrderConfirmationService (synchronous, transactional) (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises confirm() against a real provisioned tenant database:
|   • distributor confirm decrements Company + increments Distributor (R16.1);
|   • dealer confirm decrements Distributor, increments Dealer only when dealer
|     stock-holding is enabled (R16.2, R16.3);
|   • exactly one invoice under the seller's own series (R21.5);
|   • one transaction-ledger purchase appended for the buyer (R25.1);
|   • status becomes Confirmed and the order-confirmed mail is queued (R27.3);
|   • an illegal transition is rejected and nothing changes (R21.4);
|   • an oversell with backorders off rolls the whole confirmation back (R17.1);
|   • a credit-limit breach blocks confirmation and rolls everything back (R26.2).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock/order suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for confirmation tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionConfirmationTenant(array &$registry, bool $dealerStockEnabled = false, bool $backordersEnabled = false): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'conf'.$suffix,
        'subdomain' => 'conf-'.$suffix,
        'dealer_stock_enabled' => $dealerStockEnabled,
        'backorders_enabled' => $backordersEnabled,
    ]);
    $tenant->domains()->create(['domain' => 'conf-'.$suffix.'.example.test']);
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
function seedConfirmationProduct(string $sku = 'SKU-C'): int
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
 * Create a stock point owned by the given owner (Company/Distributor/Dealer).
 * Only Company defines a stockPoints() relation, so points for other owners are
 * created directly via the polymorphic owner association.
 */
function seedStockPoint(Illuminate\Database\Eloquent\Model $owner, string $name, int $sortOrder = 0): App\Models\StockPoint
{
    $point = new App\Models\StockPoint(['name' => $name, 'sort_order' => $sortOrder]);
    $point->owner()->associate($owner);
    $point->save();

    return $point;
}

/**
 * Build the service with its real collaborators.
 */
function makeConfirmationService(): OrderConfirmationService
{
    $ledger = new AccountLedgerService;

    return new OrderConfirmationService(
        new OrderStateMachine,
        new StockMovementService,
        new CreditEnforcementService($ledger, new AuditRecorder),
        new InvoiceService,
        $ledger,
        app(WhiteLabelService::class),
    );
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownConfirmationTenants(array $registry): void
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

it('confirms a distributor order: decrements company, increments distributor, invoices, ledgers, and queues the email (R16.1, R21.5, R25.1, R27.3)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionConfirmationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Dist One',
                'contact_email' => 'dist@example.test',
            ]);
            $distPoint = seedStockPoint($distributor, 'Dist Main');
            $productId = seedConfirmationProduct('SKU-DIST');

            // Seed company stock so the decrement succeeds.
            (new StockMovementService)->apply([
                App\Services\Stock\StockMovement::forOwner($company, $productId, $companyPoint->id, '100.000', 'opening'),
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

            $confirmed = makeConfirmationService()->confirm($order->load('lines'));

            // Status is Confirmed (R21.3).
            expect($confirmed->status)->toBe(OrderStatus::CONFIRMED);
            expect($order->fresh()->status)->toBe(OrderStatus::CONFIRMED);

            // Company decremented, distributor incremented (R16.1).
            $companyQty = Stock::where('stockable_type', $company->getMorphClass())
                ->where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity;
            $distQty = Stock::where('stockable_type', $distributor->getMorphClass())
                ->where('stock_point_id', $distPoint->id)->firstOrFail()->quantity;
            expect((string) $companyQty)->toBe('90.000');
            expect((string) $distQty)->toBe('10.000');

            // Two stock-ledger entries for the confirmation (out + in).
            expect(StockLedger::where('reference', 'order:'.$order->getKey())->count())->toBe(2);

            // Exactly one invoice under the seller's own series (R21.5).
            expect(Invoice::count())->toBe(1);
            $invoice = Invoice::firstOrFail();
            expect($invoice->order_id)->toBe((int) $order->getKey());
            expect($invoice->series)->toBe($company->getMorphClass().'-'.$company->getKey());
            expect($invoice->number)->toBe(1);
            expect((string) $invoice->amount)->toBe('250.00');
            expect($invoice->seller->is($company))->toBeTrue();

            // One purchase appended to the distributor's transaction ledger (R25.1).
            $entries = TransactionLedger::where('buyer_type', $distributor->getMorphClass())
                ->where('buyer_id', $distributor->getKey())->get();
            expect($entries)->toHaveCount(1);
            expect($entries[0]->entry_type)->toBe(TransactionLedger::ENTRY_PURCHASE);
            expect((string) $entries[0]->balance_after)->toBe('250.00');
        });

        // Order-confirmed email queued after commit (R27.3).
        Mail::assertQueued(OrderConfirmedMail::class);
    } finally {
        teardownConfirmationTenants($registry);
    }
});

it('confirms a dealer order with dealer stock DISABLED: decrements distributor only, no dealer stock (R16.2)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionConfirmationTenant($registry, dealerStockEnabled: false);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);
            $distPoint = seedStockPoint($distributor, 'Dist Main');
            $dealer = Dealer::create([
                'distributor_id' => $distributor->getKey(),
                'name' => 'Dealer One',
                'contact_email' => 'dealer@example.test',
            ]);
            $productId = seedConfirmationProduct('SKU-DEAL-OFF');

            (new StockMovementService)->apply([
                App\Services\Stock\StockMovement::forOwner($distributor, $productId, $distPoint->id, '50.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $dealer->getMorphClass(),
                'buyer_id' => $dealer->getKey(),
                'seller_type' => $distributor->getMorphClass(),
                'seller_id' => $distributor->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '80.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '8.000',
                'unit_price' => '10.00',
                'discount' => '0.00',
            ]);

            makeConfirmationService()->confirm($order->load('lines'));

            // Distributor decremented by the line qty (R16.2).
            $distQty = Stock::where('stockable_type', $distributor->getMorphClass())
                ->where('stock_point_id', $distPoint->id)->firstOrFail()->quantity;
            expect((string) $distQty)->toBe('42.000');

            // No dealer-owned stock exists (R16.2, R13.6).
            expect(Stock::where('stockable_type', $dealer->getMorphClass())->count())->toBe(0);

            // Only one stock-ledger entry (the decrement) for this confirmation.
            expect(StockLedger::where('reference', 'order:'.$order->getKey())->count())->toBe(1);

            // Invoice under the distributor's own series (R21.5).
            $invoice = Invoice::firstOrFail();
            expect($invoice->series)->toBe($distributor->getMorphClass().'-'.$distributor->getKey());
            expect($invoice->seller->is($distributor))->toBeTrue();
        });
    } finally {
        teardownConfirmationTenants($registry);
    }
});

it('confirms a dealer order with dealer stock ENABLED: decrements distributor and increments dealer (R16.3)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionConfirmationTenant($registry, dealerStockEnabled: true);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);
            $distPoint = seedStockPoint($distributor, 'Dist Main');
            $dealer = Dealer::create([
                'distributor_id' => $distributor->getKey(),
                'name' => 'Dealer One',
                'contact_email' => 'dealer@example.test',
            ]);
            $dealerPoint = seedStockPoint($dealer, 'Dealer Main');
            $productId = seedConfirmationProduct('SKU-DEAL-ON');

            (new StockMovementService)->apply([
                App\Services\Stock\StockMovement::forOwner($distributor, $productId, $distPoint->id, '50.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $dealer->getMorphClass(),
                'buyer_id' => $dealer->getKey(),
                'seller_type' => $distributor->getMorphClass(),
                'seller_id' => $distributor->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '70.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '7.000',
                'unit_price' => '10.00',
                'discount' => '0.00',
            ]);

            makeConfirmationService()->confirm($order->load('lines'));

            // Distributor decremented, dealer incremented (R16.3).
            $distQty = Stock::where('stockable_type', $distributor->getMorphClass())
                ->where('stock_point_id', $distPoint->id)->firstOrFail()->quantity;
            $dealerQty = Stock::where('stockable_type', $dealer->getMorphClass())
                ->where('stock_point_id', $dealerPoint->id)->firstOrFail()->quantity;
            expect((string) $distQty)->toBe('43.000');
            expect((string) $dealerQty)->toBe('7.000');

            // Both ledger entries written (out + in).
            expect(StockLedger::where('reference', 'order:'.$order->getKey())->count())->toBe(2);
        });
    } finally {
        teardownConfirmationTenants($registry);
    }
});

it('rejects an illegal confirm transition with a 422 and changes nothing (R21.4)', function () {
    $registry = [];
    $tenant = provisionConfirmationTenant($registry);

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

            $threw = null;
            try {
                makeConfirmationService()->confirm($order->load('lines'));
            } catch (IllegalOrderTransitionException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
            expect($order->fresh()->status)->toBe(OrderStatus::DELIVERED);
            expect(Invoice::count())->toBe(0);
            expect(TransactionLedger::count())->toBe(0);
        });
    } finally {
        teardownConfirmationTenants($registry);
    }
});

it('rolls the whole confirmation back when a decrement would oversell with backorders off (R17.1)', function () {
    $registry = [];
    $tenant = provisionConfirmationTenant($registry, backordersEnabled: false);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);
            seedStockPoint($distributor, 'Dist Main');
            $productId = seedConfirmationProduct('SKU-OVERSELL');

            (new StockMovementService)->apply([
                App\Services\Stock\StockMovement::forOwner($company, $productId, $companyPoint->id, '5.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '90.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '9.000',
                'unit_price' => '10.00',
                'discount' => '0.00',
            ]);

            $threw = null;
            try {
                makeConfirmationService()->confirm($order->load('lines'));
            } catch (InsufficientStockException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);

            // Nothing persisted: status, stock, invoice, ledger all untouched.
            expect($order->fresh()->status)->toBe(OrderStatus::PLACED);
            expect((string) Stock::where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity)->toBe('5.000');
            expect(Invoice::count())->toBe(0);
            expect(TransactionLedger::count())->toBe(0);
        });
    } finally {
        teardownConfirmationTenants($registry);
    }
});

it('blocks confirmation over the credit limit and rolls everything back (R26.2)', function () {
    $registry = [];
    $tenant = provisionConfirmationTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Dist One',
                'payment_terms' => ['credit_limit' => '100.00'],
            ]);
            seedStockPoint($distributor, 'Dist Main');
            $productId = seedConfirmationProduct('SKU-CREDIT');

            (new StockMovementService)->apply([
                App\Services\Stock\StockMovement::forOwner($company, $productId, $companyPoint->id, '100.000', 'opening'),
            ]);

            // Order total 150 exceeds the 100 credit limit (O=0, T=150 > L=100).
            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '150.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '10.000',
                'unit_price' => '15.00',
                'discount' => '0.00',
            ]);

            $threw = null;
            try {
                makeConfirmationService()->confirm($order->load('lines'));
            } catch (CreditLimitExceededException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);

            // Credit check runs after stock apply but inside the txn, so the
            // stock decrement is rolled back with everything else (R35.2).
            expect($order->fresh()->status)->toBe(OrderStatus::PLACED);
            expect((string) Stock::where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity)->toBe('100.000');
            expect(Invoice::count())->toBe(0);
            expect(TransactionLedger::count())->toBe(0);
        });
    } finally {
        teardownConfirmationTenants($registry);
    }
});
