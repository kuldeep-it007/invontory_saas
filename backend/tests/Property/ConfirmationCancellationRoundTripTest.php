<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Stock;
use App\Models\Tenant;
use App\Services\AccountLedgerService;
use App\Services\CreditEnforcementService;
use App\Services\InvoiceService;
use App\Services\OrderCancellationService;
use App\Services\OrderConfirmationService;
use App\Services\OrderStateMachine;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 9.19 — Property 7: Confirmation/cancellation round-trip restores stock
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 7: Confirmation/cancellation
| round-trip restores stock
|
| Validates: Requirements 18.2, 22.1
|
| Property statement (design.md §Property 7):
|   For any confirmed order, cancelling it writes reversing ledger entries that
|   restore every affected stock quantity to its exact pre-confirmation value.
|
| The requirements exercised:
|   - R18.2  Cancelling a confirmed order writes reversing ledger entries that
|            restore the confirmation's quantities.
|   - R22.1  The reversal restores every affected stock quantity to its exact
|            pre-confirmation value (exact DECIMAL(15,3), never a float
|            approximation).
|
| ONE universal invariant is generated and asserted per scenario, drawn by
| innmind/black-box (NOT hand-rolled) via the shared static::forAll(), which
| draws the mandated minimum of 100 generated scenarii per proof and shrinks any
| counterexample to a minimal failing input:
|
|   round-trip — for a generated order (1..5 lines of random positive
|       quantities; buyer a Distributor OR a Dealer; dealer stock-holding either
|       enabled or disabled) seeded with enough seller on-hand stock, SNAPSHOT
|       every stock quantity BEFORE confirming, then confirm (via the real
|       OrderConfirmationService::confirm) and cancel (via the real
|       OrderCancellationService::cancel). After the round-trip every stock
|       quantity is restored EXACTLY to its pre-confirmation value (exact decimal
|       string equality), and any stock row the confirmation created is fully
|       reversed back to zero — so the net effect across ALL stock rows is
|       precisely the starting state.
|
| Strategy. Two real tenant databases are provisioned ONCE per proof — one with
| dealer stock-holding ENABLED, one DISABLED — each seeded with a fixed
| Company → Distributor → Dealer hierarchy and one product. BlackBox then
| generates (default 100+ scenarii) a triple (lineUnits, orderKind,
| dealerStockEnabled). Per scenario we pick the matching tenant, RESET its
| mutable tables (stock, ledgers, orders, invoices) so each round-trip is
| self-contained, seed the seller's opening stock, snapshot, confirm, cancel,
| and assert exact restoration. Reusing two provisioned databases (rather than
| provisioning per scenario) keeps the 100+ iterations tractable, mirroring the
| sibling confirmation/credit property suites.
|
| DB-GATED. Confirmation + cancellation exercise real FOR UPDATE transactions in
| a per-tenant MySQL database provisioned through stancl/tenancy; when MySQL is
| unreachable the proof skips gracefully via skip_without_mysql('central').
| Every mutation runs inside $tenant->run() against the live central connection.
|
| NOTE on helper naming: multiple Property test files are loaded together by
| Pest, so every top-level helper here is prefixed `roundTrip...` to avoid
| "cannot redeclare function" collisions with sibling Feature/Property files
| (which define provisionConfirmationTenant / seedCancelReturnProduct / etc.).
*/

/**
 * Provision + migrate an isolated tenant for the round-trip proof and register
 * it for teardown. `dealerStockEnabled` selects whether a dealer buyer holds
 * stock, so the proof can cover both tenant configurations.
 *
 * @param  array<int, Tenant>  $registry
 */
function roundTripProvisionTenant(array &$registry, bool $dealerStockEnabled): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'rt'.$suffix,
        'subdomain' => 'rt-'.$suffix,
        'dealer_stock_enabled' => $dealerStockEnabled,
        'backorders_enabled' => false,
    ]);
    $tenant->domains()->create(['domain' => 'rt-'.$suffix.'.example.test']);
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
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param  array<int, Tenant>  $registry
 */
function roundTripTeardownTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 * Stock + ledger foreign keys require a real product row for every movement.
 * (Named distinctly from sibling helpers to avoid redeclaration collisions.)
 */
function roundTripSeedProduct(string $sku): int
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
 * Create a stock point owned (polymorphically) by the given owner. Only Company
 * exposes a named stockPoints() relation; Distributor/Dealer own points through
 * the same `owner` morph, created directly with the owner morph assigned.
 */
function roundTripStockPoint(\Illuminate\Database\Eloquent\Model $owner, string $name, int $sortOrder = 0): App\Models\StockPoint
{
    $point = new App\Models\StockPoint(['name' => $name, 'sort_order' => $sortOrder]);
    $point->owner()->associate($owner);
    $point->save();

    return $point;
}

/**
 * Build OrderConfirmationService with its real collaborators (no mocks) so the
 * proof exercises the genuine confirmation pipeline end to end.
 */
function roundTripConfirmationService(): OrderConfirmationService
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
 * Build OrderCancellationService with its real collaborators (no mocks).
 */
function roundTripCancellationService(): OrderCancellationService
{
    return new OrderCancellationService(
        new OrderStateMachine,
        new StockMovementService,
        new AccountLedgerService,
    );
}

/**
 * Reset every mutable table the round-trip touches so each generated scenario
 * is fully self-contained. The catalog (product + its category/collection/
 * colour) and the Company→Distributor→Dealer hierarchy + stock points are
 * seeded once and preserved; everything a confirmation/cancellation writes
 * (stock, both ledgers, orders, order lines, invoices, allocations/payments) is
 * cleared. Raw deletes are used purely for test setup to reset between
 * generated inputs even though the ledgers are append-only in production.
 */
function roundTripResetMutableState(): void
{
    $connection = DB::connection('tenant');

    foreach ([
        'payment_allocations',
        'payments',
        'transaction_ledger',
        'invoices',
        'order_lines',
        'orders',
        'stock_ledger',
        'stock',
    ] as $table) {
        if ($connection->getSchemaBuilder()->hasTable($table)) {
            $connection->table($table)->delete();
        }
    }
}

/**
 * Snapshot every current stock quantity in the active tenant as an exact
 * DECIMAL(15,3) string, keyed by (stockable_type, stockable_id, product_id,
 * stock_point_id). This captures the pre-confirmation value of every stock row,
 * so the round-trip assertion can prove each is restored exactly.
 *
 * @return array<string, string>
 */
function roundTripSnapshotStock(): array
{
    $snapshot = [];

    foreach (Stock::query()->get() as $row) {
        $key = $row->stockable_type.'|'.$row->stockable_id.'|'.$row->product_id.'|'.$row->stock_point_id;
        $snapshot[$key] = (string) $row->quantity;
    }

    return $snapshot;
}

it('restores every affected stock quantity to its exact pre-confirmation value after confirming then cancelling any order (R18.2, R22.1)', function () {
    skip_without_mysql('central');

    Mail::fake();

    $registry = [];

    try {
        // Provision ONE tenant per dealer-stock configuration up front, each
        // with a fixed hierarchy + catalog, so the 100+ scenarii reuse two
        // databases instead of provisioning per scenario.
        $tenants = [
            true => roundTripProvisionTenant($registry, true),
            false => roundTripProvisionTenant($registry, false),
        ];

        static::forAll(
            // A multi-line order: 1..5 lines, each a positive unit quantity. Each
            // line is what the confirmation moves and the cancellation must undo.
            Set::sequence(Set::integers()->between(1, 500))->between(1, 5),
            // Buyer tier: a Distributor order (buyer = Distributor, always holds
            // stock) or a Dealer order (buyer = Dealer).
            Set::of('distributor', 'dealer'),
            // Whether dealer stock-holding is enabled for the tenant. Selects
            // which provisioned tenant runs the scenario, so both tenant
            // configurations are exercised.
            Set::of(true, false),
        )->then(function (array $lineUnits, string $orderKind, bool $dealerStockEnabled) use ($tenants) {
            $tenants[$dealerStockEnabled]->run(function () use ($lineUnits, $orderKind) {
                // Reset all mutable state so this round-trip is self-contained,
                // then seed a fresh hierarchy + catalog for the scenario. The
                // confirmation/cancellation touch stock by (owner, product,
                // point), so distinct owners/products per scenario keep each
                // round-trip's before/after snapshots independent.
                roundTripResetMutableState();

                $company = Company::create(['name' => 'RoundTrip Co '.bin2hex(random_bytes(3))]);
                $companyPoint = roundTripStockPoint($company, 'Co Main');
                $distributor = Distributor::create([
                    'company_id' => $company->getKey(),
                    'name' => 'Dist One',
                ]);
                $distPoint = roundTripStockPoint($distributor, 'Dist Main');
                $dealer = Dealer::create([
                    'distributor_id' => $distributor->getKey(),
                    'name' => 'Dealer One',
                ]);
                roundTripStockPoint($dealer, 'Dealer Main');

                $productId = roundTripSeedProduct('RT-'.bin2hex(random_bytes(3)));

                $totalUnits = array_sum($lineUnits);
                $totalQty = number_format($totalUnits, 3, '.', '');

                // The seller for this order kind. A Distributor order sells from
                // the Company to the Distributor (R16.1); a Dealer order sells
                // from the Distributor to the Dealer (R16.2 / R16.3).
                if ($orderKind === 'distributor') {
                    $seller = $company;
                    $sellerPoint = $companyPoint;
                    $buyer = $distributor;
                } else {
                    $seller = $distributor;
                    $sellerPoint = $distPoint;
                    $buyer = $dealer;
                }

                // Seed the seller's opening stock so the confirmation decrement
                // has real on-hand quantity to move (backorders are disabled, so
                // without enough stock the confirmation would be rejected).
                (new StockMovementService)->apply([
                    StockMovement::forOwner($seller, $productId, (int) $sellerPoint->getKey(), $totalQty, 'opening'),
                ]);

                // The order: priced so each line's unit_price keeps the math
                // simple; the exact total does not matter to this proof (which
                // concerns STOCK restoration), only the stock movements do.
                $total = number_format($totalUnits * 10, 2, '.', '');
                $order = Order::create([
                    'buyer_type' => $buyer->getMorphClass(),
                    'buyer_id' => $buyer->getKey(),
                    'seller_type' => $seller->getMorphClass(),
                    'seller_id' => $seller->getKey(),
                    'status' => OrderStatus::PLACED,
                    'order_type' => 'standard',
                    'total' => $total,
                ]);

                foreach ($lineUnits as $units) {
                    OrderLine::create([
                        'order_id' => $order->getKey(),
                        'product_id' => $productId,
                        'quantity' => number_format($units, 3, '.', ''),
                        'unit_price' => '10.00',
                        'discount' => '0.00',
                    ]);
                }

                // SNAPSHOT every stock quantity BEFORE confirmation. This is the
                // exact pre-confirmation value the round-trip must restore.
                $before = roundTripSnapshotStock();

                // Confirm (real pipeline): decrements the seller and, where the
                // buyer holds stock, increments the buyer.
                roundTripConfirmationService()->confirm($order->load('lines'));

                // Cancel (real pipeline): writes reversing ledger entries that
                // restore every affected stock quantity (R18.2, R22.1).
                $cancelled = roundTripCancellationService()->cancel($order->fresh());
                expect($cancelled->status)->toBe(OrderStatus::CANCELLED);

                $after = roundTripSnapshotStock();

                // INVARIANT (R18.2, R22.1): every stock quantity that existed
                // before confirmation is restored to its EXACT pre-confirmation
                // value (exact decimal string equality).
                foreach ($before as $key => $qty) {
                    expect($after[$key] ?? null)->toBe(
                        $qty,
                        "stock row {$key} was not restored to its pre-confirmation value",
                    );
                }

                // And any stock row the confirmation CREATED (e.g. a buyer
                // increment) is fully reversed back to zero, so the net effect
                // across ALL stock rows is exactly the starting state.
                foreach ($after as $key => $qty) {
                    if (! array_key_exists($key, $before)) {
                        expect($qty)->toBe('0.000', "stock row {$key} created by confirmation was not fully reversed");
                    }
                }
            });
        });
    } finally {
        roundTripTeardownTenants($registry);
    }
});
