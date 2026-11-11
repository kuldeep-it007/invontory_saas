<?php

declare(strict_types=1);

use App\Exceptions\IllegalOrderTransitionException;
use App\Exceptions\InvalidReturnException;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
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
use App\Services\OrderCancellationService;
use App\Services\OrderConfirmationService;
use App\Services\OrderReturnService;
use App\Services\OrderStateMachine;
use App\Services\StockMovementService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Task 9.18 — Order cancellation and returns reversal (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises OrderCancellationService + OrderReturnService against a real
| provisioned tenant database:
|   • cancelling a confirmed order restores every affected stock quantity to its
|     exact pre-confirmation value and reverses the buyer account (R18.2, R22.1);
|   • an illegal cancel is rejected and nothing changes (R21.4);
|   • a return re-enters returned quantities into stock, writes a return entry
|     against the buyer account, and recomputes Outstanding_Balance (R18.3,
|     R22.2, R22.3);
|   • a return line for a product not on the order is rejected with a 422.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the confirmation suite. Helpers carry a
| file-unique `CancelReturn` prefix to avoid redeclaration collisions.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * @param  array<int, Tenant>  $registry
 */
function provisionCancelReturnTenant(array &$registry, bool $dealerStockEnabled = false, bool $backordersEnabled = false): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cr'.$suffix,
        'subdomain' => 'cr-'.$suffix,
        'dealer_stock_enabled' => $dealerStockEnabled,
        'backorders_enabled' => $backordersEnabled,
    ]);
    $tenant->domains()->create(['domain' => 'cr-'.$suffix.'.example.test']);
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

function seedCancelReturnProduct(string $sku = 'SKU-CR'): int
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

function seedCancelReturnStockPoint(Illuminate\Database\Eloquent\Model $owner, string $name, int $sortOrder = 0): App\Models\StockPoint
{
    $point = new App\Models\StockPoint(['name' => $name, 'sort_order' => $sortOrder]);
    $point->owner()->associate($owner);
    $point->save();

    return $point;
}

function makeCancelReturnConfirmationService(): OrderConfirmationService
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

function makeCancellationService(): OrderCancellationService
{
    return new OrderCancellationService(
        new OrderStateMachine,
        new StockMovementService,
        new AccountLedgerService,
    );
}

function makeReturnService(): OrderReturnService
{
    return new OrderReturnService(
        new StockMovementService,
        new AccountLedgerService,
    );
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownCancelReturnTenants(array $registry): void
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

it('cancelling a confirmed distributor order restores stock to its pre-confirmation value and reverses the account (R18.2, R22.1)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionCancelReturnTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);
            $distPoint = seedCancelReturnStockPoint($distributor, 'Dist Main');
            $productId = seedCancelReturnProduct('SKU-CANCEL');

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

            makeCancelReturnConfirmationService()->confirm($order->load('lines'));

            // Pre-cancel sanity: company 90, distributor 10, outstanding 250.
            expect((string) Stock::where('stockable_type', $company->getMorphClass())
                ->where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity)->toBe('90.000');
            expect((string) Stock::where('stockable_type', $distributor->getMorphClass())
                ->where('stock_point_id', $distPoint->id)->firstOrFail()->quantity)->toBe('10.000');
            expect((new AccountLedgerService)->outstandingBalance($distributor))->toBe('250.00');

            $cancelled = makeCancellationService()->cancel($order->fresh());

            expect($cancelled->status)->toBe(OrderStatus::CANCELLED);

            // Stock restored to EXACT pre-confirmation values (R18.2, R22.1).
            expect((string) Stock::where('stockable_type', $company->getMorphClass())
                ->where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity)->toBe('100.000');
            expect((string) Stock::where('stockable_type', $distributor->getMorphClass())
                ->where('stock_point_id', $distPoint->id)->firstOrFail()->quantity)->toBe('0.000');

            // Reversing ledger entries written under the cancel reference.
            expect(StockLedger::where('reference', 'order-cancel:'.$order->getKey())->count())->toBe(2);

            // Account reversed: the cancelled purchase no longer counts (R22.1).
            expect((new AccountLedgerService)->outstandingBalance($distributor))->toBe('0.00');
            $cancelEntry = TransactionLedger::where('buyer_type', $distributor->getMorphClass())
                ->where('buyer_id', $distributor->getKey())
                ->where('entry_type', TransactionLedger::ENTRY_CANCELLATION)->firstOrFail();
            expect((string) $cancelEntry->amount)->toBe('-250.00');
        });
    } finally {
        teardownCancelReturnTenants($registry);
    }
});

it('rejects an illegal cancel (Placed order has no stock to reverse? still legal) but rejects cancel from a terminal state with a 422 (R21.4)', function () {
    $registry = [];
    $tenant = provisionCancelReturnTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);

            // Already Closed — `cancel` is undefined from a terminal state.
            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::CLOSED,
                'order_type' => 'standard',
                'total' => '10.00',
            ]);

            $threw = null;
            try {
                makeCancellationService()->cancel($order->fresh());
            } catch (IllegalOrderTransitionException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
            expect($order->fresh()->status)->toBe(OrderStatus::CLOSED);
            expect(TransactionLedger::count())->toBe(0);
            expect(StockLedger::count())->toBe(0);
        });
    } finally {
        teardownCancelReturnTenants($registry);
    }
});

it('processing a return re-enters stock into the seller, writes a return entry, and recomputes Outstanding_Balance (R18.3, R22.2, R22.3)', function () {
    $registry = [];
    $tenant = provisionCancelReturnTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);
            $distPoint = seedCancelReturnStockPoint($distributor, 'Dist Main');
            $productId = seedCancelReturnProduct('SKU-RETURN');

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

            makeCancelReturnConfirmationService()->confirm($order->load('lines'));
            // After confirm: company 90, distributor 10, outstanding 250.

            // Return 4 units of the product. Credited amount defaults to
            // net unit (25.00) × 4 = 100.00.
            $summary = makeReturnService()->process($order->fresh(), [
                ['product_id' => $productId, 'quantity' => '4.000'],
            ]);

            // Seller re-entered (goods back): 90 + 4 = 94. Buyer removed: 10 - 4 = 6.
            expect((string) Stock::where('stockable_type', $company->getMorphClass())
                ->where('stock_point_id', $companyPoint->id)->firstOrFail()->quantity)->toBe('94.000');
            expect((string) Stock::where('stockable_type', $distributor->getMorphClass())
                ->where('stock_point_id', $distPoint->id)->firstOrFail()->quantity)->toBe('6.000');

            // Return ledger entries written (seller in + buyer out).
            expect(StockLedger::where('reference', 'order-return:'.$order->getKey())->count())->toBe(2);

            // Account adjusted by a return entry; Outstanding_Balance recomputed:
            // 250 - 100 = 150 (R22.2, R22.3).
            $returnEntry = TransactionLedger::where('buyer_type', $distributor->getMorphClass())
                ->where('buyer_id', $distributor->getKey())
                ->where('entry_type', TransactionLedger::ENTRY_RETURN)->firstOrFail();
            expect((string) $returnEntry->amount)->toBe('-100.00');
            expect($summary['outstanding_balance'])->toBe('150.00');
            expect($summary['credited_amount'])->toBe('100.00');
            expect((new AccountLedgerService)->outstandingBalance($distributor))->toBe('150.00');
        });
    } finally {
        teardownCancelReturnTenants($registry);
    }
});

it('rejects a return line for a product not on the order with a 422 and changes nothing (R18.3)', function () {
    $registry = [];
    $tenant = provisionCancelReturnTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create(['company_id' => $company->getKey(), 'name' => 'Dist One']);
            seedCancelReturnStockPoint($distributor, 'Dist Main');
            $productId = seedCancelReturnProduct('SKU-ON-ORDER');
            $otherProductId = seedCancelReturnProduct('SKU-NOT-ON-ORDER');

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
                'total' => '50.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '2.000',
                'unit_price' => '25.00',
                'discount' => '0.00',
            ]);

            makeCancelReturnConfirmationService()->confirm($order->load('lines'));
            $balanceBefore = (new AccountLedgerService)->outstandingBalance($distributor);
            $returnLedgerBefore = StockLedger::where('reference', 'order-return:'.$order->getKey())->count();

            $threw = null;
            try {
                makeReturnService()->process($order->fresh(), [
                    ['product_id' => $otherProductId, 'quantity' => '1.000'],
                ]);
            } catch (InvalidReturnException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);

            // Nothing changed: no return ledger entries, account unchanged.
            expect(StockLedger::where('reference', 'order-return:'.$order->getKey())->count())->toBe($returnLedgerBefore);
            expect((new AccountLedgerService)->outstandingBalance($distributor))->toBe($balanceBefore);
            expect(TransactionLedger::where('entry_type', TransactionLedger::ENTRY_RETURN)->count())->toBe(0);
        });
    } finally {
        teardownCancelReturnTenants($registry);
    }
});
