<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 7.15 — Property 6: Atomic all-or-nothing writes
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 6: Atomic all-or-nothing
| writes
|
| Validates: Requirements 14.2, 16.4, 35.2, 35.3
|
| Property statement (design.md §Property 6):
|   For any inventory operation that fails at any point before completion, the
|   system rolls back entirely so that NO stock quantity change and NO
|   stock_ledger entry from that operation persists.
|
|   - R14.2  IF the Stock quantity increase for a stock inward fails, THEN the
|            Platform rolls back the associated Stock_Ledger entry and returns an
|            error.
|   - R16.4  Order-confirmation stock decrements/increments and ledger writes are
|            performed within a SINGLE database transaction.
|   - R35.2  WHEN an inventory operation fails partway, the Platform rolls back
|            the ENTIRE operation so no partial changes persist.
|   - R35.3  Inventory write operations execute within atomic, recoverable
|            database transactions.
|
| Design reference: "Inventory Engine" — StockMovementService::apply() wraps the
| whole batch in one DB transaction, locks every affected stock row FOR UPDATE in
| deterministic primary-key order, applies all increments/decrements, appends one
| immutable ledger entry per movement, and rolls the ENTIRE batch back on any
| failure (R14.2, R16.4, R35.2, R35.3).
|
| The universal invariant under test:
|
|     snapshot(stock, stock_ledger) BEFORE a failing operation
|       == snapshot(stock, stock_ledger) AFTER it
|
| i.e. whatever partial increments/decrements and ledger inserts a failing batch
| attempted, the committed state is byte-for-byte identical to the pre-operation
| state.
|
| Two independent failure MODES are generated so the roll-back is proven for a
| failure detected BEFORE any row is mutated AND for a failure injected AFTER some
| rows have already been written inside the transaction:
|
|   mode 0 — negative-stock rejection (backorders disabled): a generated line in
|            the batch drives an owner's stock below zero, so the whole batch is
|            rejected with InsufficientStockException and nothing persists (R17.1
|            feeding the R35.2 roll-back).
|   mode 1 — injected mid-transaction failure: a generated line references a
|            product id that violates the ledger's product_id foreign key, so the
|            INSERT throws AFTER earlier movements in the same batch have already
|            saved their stock row + ledger entry — proving those partial writes
|            are rolled back too (R14.2, R35.2, R35.3).
|
| Generation uses innmind/black-box (NOT hand-rolled) via the shared
| static::forAll(), which draws the mandated minimum of 100 generated scenarii per
| proof and shrinks any counterexample to a minimal failing input.
|
| DB-GATED. Provisioning a real per-tenant MySQL database (and exercising a real
| FOR UPDATE transaction with a real rollback) needs a reachable MySQL server;
| when MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'). Assertions are written to be correct against a
| real MySQL instance using the live central connection.
*/

/**
 * Provision an isolated tenant with a migrated tenant database and register it
 * for teardown. Mirrors the other DB-gated property/feature proofs' helpers.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionAtomicWritesPropTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'atom'.$suffix,
        'subdomain' => 'atom-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'atom-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    $registry[] = $tenant;

    return $tenant;
}

/**
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param  array<int, Tenant>  $tenants
 */
function teardownAtomicWritesPropTenants(array $tenants): void
{
    tenancy()->end();

    foreach ($tenants as $t) {
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
 * Stock + ledger rows reference a real product because the ledger's product_id
 * foreign key is enforced once the catalog (task 7.4) is migrated — which is
 * exactly what failure mode 1 relies on to inject a mid-transaction error.
 */
function seedAtomicWritesProduct(string $sku): int
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
 * Snapshot the complete committed state of the two tables the operation may
 * touch, as a deterministic, order-independent fingerprint. Comparing the
 * before/after snapshots proves nothing changed — neither a stock quantity nor
 * a single ledger row.
 *
 * @return array{stock: array<string, string>, ledger_rows: list<array<string, mixed>>, ledger_count: int}
 */
function atomicWritesSnapshot(): array
{
    $stock = [];
    foreach (Stock::query()->orderBy('id')->get() as $row) {
        $key = implode('|', [
            $row->stockable_type,
            (int) $row->stockable_id,
            (int) $row->product_id,
            (int) $row->stock_point_id,
        ]);
        $stock[$key] = (string) $row->quantity;
    }
    ksort($stock);

    $ledgerRows = StockLedger::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StockLedger $e) => [
            'id' => $e->id,
            'product_id' => $e->product_id,
            'stock_point_id' => $e->stock_point_id,
            'movement_type' => $e->movement_type,
            'quantity' => (string) $e->quantity,
            'balance_after' => (string) $e->balance_after,
        ])
        ->all();

    return [
        'stock' => $stock,
        'ledger_rows' => $ledgerRows,
        'ledger_count' => count($ledgerRows),
    ];
}

it('rolls back entirely so no stock or ledger change persists when an inventory operation fails partway (R14.2, R16.4, R35.2, R35.3)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $tenant = provisionAtomicWritesPropTenant($tenants);

        $tenant->run(function () {
            // The tenant's single Company (R1.5) that owns the master stock.
            $company = Company::create(['name' => 'Atomic Writes Co', 'gstin' => 'GST-ATOM']);

            // Two stock points so generated batches can span multiple rows and a
            // partial write touches a different row than the failing one.
            $pointA = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $pointB = $company->stockPoints()->create(['name' => 'Overflow', 'sort_order' => 1]);

            // Two real products; a third, invalid product id is used to inject a
            // foreign-key failure in mode 1.
            $productX = seedAtomicWritesProduct('ATOM-X');
            $productY = seedAtomicWritesProduct('ATOM-Y');
            $invalidProductId = max($productX, $productY) + 10_000; // no such product row

            $pointIds = [$pointA->id, $pointB->id];
            $productIds = [$productX, $productY];

            $service = new StockMovementService;

            // ----------------------------------------------------------------
            // Generated scenario (100+ randomized iterations):
            //   openA/openB   ∈ 0..50   opening stock seeded on each point for X
            //   decrement     ∈ 1..80   magnitude of an outward line
            //   good1/good2   ∈ 1..40   magnitudes of two lines that WOULD persist
            //   p1/p2         ∈ {0,1}   which product the good lines target
            //   sp1/sp2       ∈ {0,1}   which stock point the good lines target
            //   mode          ∈ {0,1}   failure mode (negative vs injected FK error)
            // ----------------------------------------------------------------
            static::forAll(
                Set::integers()->between(0, 50),
                Set::integers()->between(0, 50),
                Set::integers()->between(1, 80),
                Set::integers()->between(1, 40),
                Set::integers()->between(1, 40),
                Set::integers()->between(0, 1),
                Set::integers()->between(0, 1),
                Set::integers()->between(0, 1),
                Set::integers()->between(0, 1),
                Set::integers()->between(0, 1),
            )->then(function (
                int $openA,
                int $openB,
                int $decrement,
                int $good1,
                int $good2,
                int $p1,
                int $p2,
                int $sp1,
                int $sp2,
                int $mode,
            ) use ($service, $company, $pointIds, $productIds, $pointA, $productX, $productY, $invalidProductId) {
                // Reset the tenant tables so each generated scenario starts from
                // a known, isolated state (no bleed between iterations). The
                // ledger is append-only (R15.2), so its model-level delete guard
                // is bypassed here with a raw truncate purely for test setup.
                DB::connection('tenant')->table('stock_ledger')->delete();
                DB::connection('tenant')->table('stock')->delete();

                // Seed opening balances via a SUCCESSFUL, committed operation so
                // there is real pre-existing state the failing op must preserve.
                $opening = [];
                if ($openA > 0) {
                    $opening[] = StockMovement::forOwner($company, $productX, $pointA->id, number_format($openA, 3, '.', ''), 'opening');
                }
                if ($openB > 0) {
                    $opening[] = StockMovement::forOwner($company, $productX, $pointIds[1], number_format($openB, 3, '.', ''), 'opening');
                }
                if ($opening !== []) {
                    $service->apply($opening);
                }

                // Snapshot the committed state immediately BEFORE the failing op.
                $before = atomicWritesSnapshot();

                // Build a multi-movement batch whose FIRST lines would persist
                // (increments on product X / both points) followed by a line that
                // forces the whole batch to fail. The good lines are confined to
                // product X so they can NEVER net against the failing line's
                // stock key (product Y / invalid product), keeping the forced
                // failure deterministic across all generated inputs.
                $batch = [
                    StockMovement::forOwner($company, $productX, $pointIds[$sp1], number_format($good1, 3, '.', ''), 'received', null, 'ATOM-OP'),
                    StockMovement::forOwner($company, $productX, $pointIds[$sp2], number_format($good2, 3, '.', ''), 'received', null, 'ATOM-OP'),
                ];

                $expectedException = null;

                if ($mode === 0) {
                    // Mode 0: drive stock negative on point A for product Y —
                    // which has NO opening stock and NO positive line in this
                    // batch, so any negative delta is guaranteed to breach zero
                    // with backorders disabled → InsufficientStockException, the
                    // whole batch rejected before any mutation (R17.1 → R35.2).
                    $batch[] = StockMovement::forOwner(
                        $company,
                        $productY,
                        $pointA->id,
                        number_format(-$decrement, 3, '.', ''),
                        'sale_out',
                        null,
                        'ATOM-OP'
                    );
                    $expectedException = InsufficientStockException::class;
                } else {
                    // Mode 1: inject a failure AFTER the good lines have already
                    // saved their stock row + ledger entry inside the open
                    // transaction. A movement for a non-existent product_id makes
                    // the ledger INSERT violate its foreign key and throw, so the
                    // engine must roll back the earlier partial writes too.
                    $batch[] = StockMovement::forOwner(
                        $company,
                        $invalidProductId,
                        $pointA->id,
                        '1.000',
                        'received',
                        null,
                        'ATOM-OP'
                    );
                    // A QueryException (FK violation) is expected; its concrete
                    // class is the framework's, so we assert on "any Throwable".
                }

                // Execute the failing operation and require that it DID fail.
                $threw = null;
                try {
                    $service->apply($batch);
                } catch (\Throwable $e) {
                    $threw = $e;
                }

                expect($threw)->not->toBeNull();

                if ($expectedException !== null) {
                    expect($threw)->toBeInstanceOf($expectedException);
                }

                // Core invariant (R35.2/R35.3): the committed state AFTER the
                // failed operation is identical to the snapshot taken BEFORE it —
                // no stock quantity moved and no ledger entry was appended.
                $after = atomicWritesSnapshot();

                expect($after['stock'])->toBe($before['stock']);
                expect($after['ledger_count'])->toBe($before['ledger_count']);
                expect($after['ledger_rows'])->toBe($before['ledger_rows']);

                // R14.2 explicitly: the ledger entries the batch attempted to
                // append (reference 'ATOM-OP') left NO trace.
                expect(StockLedger::query()->where('reference', 'ATOM-OP')->count())->toBe(0);
            });
        });
    } finally {
        teardownAtomicWritesPropTenants($tenants);
    }
});
