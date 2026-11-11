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
| Task 9.6 — Property 4: Negative-stock prevention is all-or-nothing
|            (single operation)
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 4: Negative-stock
| prevention is all-or-nothing (single operation)
|
| Validates: Requirements 17.1, 17.2
|
| Property statement (design.md §Property 4):
|   For any order confirmation where at least one line would drive stock below
|   zero and backorders are DISABLED, the entire confirmation is rejected and no
|   stock quantity or ledger entry changes persist; where backorders are ENABLED
|   the confirmation succeeds and the recorded backordered quantity equals the
|   shortfall.
|
| The requirements exercised:
|   - R17.1  IF confirming an Order would reduce ANY Stock quantity below zero
|            and backorders are DISABLED for the Tenant, THEN the Platform
|            rejects the ENTIRE confirmation and applies NO Stock changes.
|   - R17.2  WHERE backorders are ENABLED for the Tenant, the Platform permits a
|            confirmation that reduces Stock below zero and RECORDS the resulting
|            backordered quantity.
|
| StockMovementService::apply() honors the per-tenant `backorders_enabled`
| toggle (task 9.5): with NO explicit `allow_negative` context, the guard is
| driven by the active tenant's central flag. This proof therefore provisions
| TWO real tenants — one with backorders OFF, one with backorders ON — and drives
| apply() with no context so the live toggle governs the outcome.
|
| Two universal invariants are generated per scenario and drawn by
| innmind/black-box (NOT hand-rolled) via the shared static::forAll(), which
| draws the mandated minimum of 100 generated scenarii per proof and shrinks any
| counterexample to a minimal failing input:
|
|   backorders-OFF — a batch of decrements across several (product, point) stock
|       keys is generated against generated seeded quantities S. When AT LEAST
|       ONE key's net result would fall below zero, the WHOLE batch is rejected
|       with InsufficientStockException (HTTP 422) and NOTHING persists: every
|       stock quantity and the entire ledger are byte-for-byte identical to the
|       pre-operation snapshot (R17.1). When no key would go negative the batch
|       succeeds and every resulting quantity is exactly S - demand.
|
|   backorders-ON — the SAME negative-driving batch, applied for a tenant whose
|       `backorders_enabled` flag is ON, SUCCEEDS; for every key driven below
|       zero the recorded resulting quantity (and the last ledger entry's
|       balance_after for that key) is negative and its magnitude |balance_after|
|       equals the shortfall exactly, i.e. demand - S (R17.2).
|
| DB-GATED. Provisioning a real per-tenant MySQL database and exercising a real
| FOR UPDATE transaction with a real rollback needs a reachable MySQL server with
| CREATE DATABASE privileges and the stancl/tenancy MySQL manager; when MySQL is
| unreachable the proof skips gracefully via skip_without_mysql('central').
| Assertions are written to be correct against a real MySQL instance using the
| live central connection, and every mutation runs inside $tenant->run().
|
| NOTE on helper naming: multiple Property test files are loaded together by
| Pest, so every top-level helper here is prefixed `negStock...` to avoid
| "cannot redeclare function" collisions with sibling files (which define
| provisionAtomicWritesPropTenant / seedAtomicWritesProduct /
| stockConservationSeedProduct, etc.).
*/

/**
 * Provision + migrate an isolated tenant for the negative-stock proof with its
 * central `backorders_enabled` toggle set as requested, and register it for
 * teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function negStockProvisionTenant(array &$registry, bool $backordersEnabled): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'neg'.$suffix,
        'subdomain' => 'neg-'.$suffix,
        'backorders_enabled' => $backordersEnabled,
    ]);
    $tenant->domains()->create(['domain' => 'neg-'.$suffix.'.example.test']);
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
function negStockTeardownTenants(array $registry): void
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
 * Stock + ledger product_id foreign keys are enforced, so a real product row is
 * required for every movement. (Named distinctly from sibling helpers
 * seedAtomicWritesProduct / stockConservationSeedProduct to avoid redeclaration
 * collisions when Pest loads the whole Property suite.)
 */
function negStockSeedProduct(string $sku): int
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
 * before/after snapshots proves nothing changed when a batch is rejected —
 * neither a stock quantity nor a single ledger row (R17.1 all-or-nothing).
 *
 * @return array{stock: array<string, string>, ledger_rows: list<array<string, mixed>>, ledger_count: int}
 */
function negStockSnapshot(): array
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

/**
 * Normalize an integer quantity to a DECIMAL(15,3) string, e.g. 7 => "7.000".
 */
function negStockQty(int $units): string
{
    return number_format($units, 3, '.', '');
}

/**
 * The live on-hand quantity (DECIMAL(15,3) string) for an owner/product/point,
 * or "0.000" when no stock row exists. Reads straight from the committed stock
 * table so it reflects exactly what the movement persisted.
 */
function negStockQuantity(Company $owner, int $productId, int $stockPointId): string
{
    $row = Stock::query()
        ->where('stockable_type', $owner->getMorphClass())
        ->where('stockable_id', $owner->getKey())
        ->where('product_id', $productId)
        ->where('stock_point_id', $stockPointId)
        ->first();

    return $row === null ? '0.000' : (string) $row->quantity;
}

it('rejects the entire confirmation and persists nothing when any line would go below zero with backorders OFF (R17.1)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = negStockProvisionTenant($registry, backordersEnabled: false);

        $tenant->run(function () {
            // The tenant's single Company (R1.5) owns the master stock. Three
            // (product, point) stock keys let a generated batch span multiple
            // rows so "ANY line below zero rejects the WHOLE batch" is exercised.
            $company = Company::create(['name' => 'Negative Guard Co', 'gstin' => 'GST-NEG-OFF']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            $products = [
                negStockSeedProduct('NEG-OFF-A'),
                negStockSeedProduct('NEG-OFF-B'),
                negStockSeedProduct('NEG-OFF-C'),
            ];

            $service = new StockMovementService;

            // ----------------------------------------------------------------
            // Generated scenario (100+ randomized iterations):
            //   s1,s2,s3  ∈ 0..40   seeded opening stock S per product
            //   d1,d2,d3  ∈ 0..60   decrement demand D per product (0 = no line)
            // A line goes below zero iff D > S for that product. The batch is
            // negative-driving iff ANY product has D > S.
            // ----------------------------------------------------------------
            static::forAll(
                Set::integers()->between(0, 40),
                Set::integers()->between(0, 40),
                Set::integers()->between(0, 40),
                Set::integers()->between(0, 60),
                Set::integers()->between(0, 60),
                Set::integers()->between(0, 60),
            )->then(function (
                int $s1,
                int $s2,
                int $s3,
                int $d1,
                int $d2,
                int $d3,
            ) use ($service, $company, $point, $products) {
                // Isolate each generated scenario: clear stock + ledger so the
                // before/after snapshot is self-contained per iteration. The
                // ledger is append-only (R15.2); a raw delete is used purely for
                // test setup to reset between generated inputs.
                DB::connection('tenant')->table('stock_ledger')->delete();
                DB::connection('tenant')->table('stock')->delete();

                $seeds = [$s1, $s2, $s3];
                $demands = [$d1, $d2, $d3];

                // Seed opening balances via a SUCCESSFUL, committed operation so
                // the negative-driving batch has real pre-existing state to leave
                // untouched when it is rejected.
                $opening = [];
                foreach ($products as $i => $productId) {
                    if ($seeds[$i] > 0) {
                        $opening[] = StockMovement::forOwner(
                            $company, $productId, $point->id, negStockQty($seeds[$i]), 'opening'
                        );
                    }
                }
                if ($opening !== []) {
                    $service->apply($opening);
                }

                // Snapshot the committed state immediately BEFORE the batch.
                $before = negStockSnapshot();

                // Build the decrement batch (one line per product with D > 0) and
                // decide, deterministically, whether ANY line would go negative.
                $batch = [];
                $anyNegative = false;
                foreach ($products as $i => $productId) {
                    if ($demands[$i] > 0) {
                        $batch[] = StockMovement::forOwner(
                            $company, $productId, $point->id, '-'.negStockQty($demands[$i]), 'sale_out', null, 'NEG-OFF'
                        );
                        if ($demands[$i] > $seeds[$i]) {
                            $anyNegative = true;
                        }
                    }
                }

                // An empty batch (all demands 0) is a no-op; nothing to assert
                // beyond the snapshot being unchanged, which trivially holds.
                if ($batch === []) {
                    return;
                }

                $threw = null;
                try {
                    // No context: the per-tenant toggle (OFF) governs the guard.
                    $service->apply($batch);
                } catch (InsufficientStockException $e) {
                    $threw = $e;
                }

                if ($anyNegative) {
                    // R17.1: the ENTIRE confirmation is rejected with a 422 and
                    // NOTHING persists — the committed state is byte-for-byte the
                    // pre-operation snapshot.
                    expect($threw)->not->toBeNull();
                    expect($threw->getStatusCode())->toBe(422);

                    $after = negStockSnapshot();
                    expect($after['stock'])->toBe($before['stock']);
                    expect($after['ledger_count'])->toBe($before['ledger_count']);
                    expect($after['ledger_rows'])->toBe($before['ledger_rows']);

                    // The batch's ledger entries (reference 'NEG-OFF') left no trace.
                    expect(StockLedger::query()->where('reference', 'NEG-OFF')->count())->toBe(0);
                } else {
                    // No line would go below zero: the batch is accepted and each
                    // resulting quantity is exactly S - demand (the counterpart to
                    // the all-or-nothing rejection — a safe batch is NOT rejected).
                    expect($threw)->toBeNull();

                    foreach ($products as $i => $productId) {
                        expect(negStockQuantity($company, $productId, $point->id))
                            ->toBe(negStockQty($seeds[$i] - $demands[$i]));
                    }
                }
            });
        });
    } finally {
        negStockTeardownTenants($registry);
    }
});

it('succeeds and records a backordered quantity equal to the shortfall when any line goes below zero with backorders ON (R17.2)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = negStockProvisionTenant($registry, backordersEnabled: true);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Backorder Co', 'gstin' => 'GST-NEG-ON']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            $products = [
                negStockSeedProduct('NEG-ON-A'),
                negStockSeedProduct('NEG-ON-B'),
                negStockSeedProduct('NEG-ON-C'),
            ];

            $service = new StockMovementService;

            // ----------------------------------------------------------------
            // Generated scenario (100+ randomized iterations):
            //   s1,s2,s3  ∈ 0..40   seeded opening stock S per product
            //   e1,e2,e3  ∈ 1..60   EXTRA demand beyond S per product, so the
            //                       decrement D = S + extra is GUARANTEED to drive
            //                       every line below zero. The shortfall for each
            //                       line is therefore exactly `extra` = D - S.
            // ----------------------------------------------------------------
            static::forAll(
                Set::integers()->between(0, 40),
                Set::integers()->between(0, 40),
                Set::integers()->between(0, 40),
                Set::integers()->between(1, 60),
                Set::integers()->between(1, 60),
                Set::integers()->between(1, 60),
            )->then(function (
                int $s1,
                int $s2,
                int $s3,
                int $e1,
                int $e2,
                int $e3,
            ) use ($service, $company, $point, $products) {
                DB::connection('tenant')->table('stock_ledger')->delete();
                DB::connection('tenant')->table('stock')->delete();

                $seeds = [$s1, $s2, $s3];
                $extras = [$e1, $e2, $e3];

                // Seed opening balances.
                $opening = [];
                foreach ($products as $i => $productId) {
                    if ($seeds[$i] > 0) {
                        $opening[] = StockMovement::forOwner(
                            $company, $productId, $point->id, negStockQty($seeds[$i]), 'opening'
                        );
                    }
                }
                if ($opening !== []) {
                    $service->apply($opening);
                }

                // Decrement each product by S + extra, guaranteeing every line
                // breaches zero by exactly `extra` (the shortfall).
                $batch = [];
                foreach ($products as $i => $productId) {
                    $demand = $seeds[$i] + $extras[$i];
                    $batch[] = StockMovement::forOwner(
                        $company, $productId, $point->id, '-'.negStockQty($demand), 'sale_out', null, 'NEG-ON'
                    );
                }

                // No context: the per-tenant toggle (ON) permits going negative.
                // The confirmation SUCCEEDS — no exception is thrown (R17.2).
                $service->apply($batch);

                foreach ($products as $i => $productId) {
                    $shortfall = $extras[$i]; // D - S

                    // The recorded resulting negative quantity equals -shortfall,
                    // so the backordered quantity |balance_after| == shortfall.
                    $expectedBalance = '-'.negStockQty($shortfall);

                    expect(negStockQuantity($company, $productId, $point->id))->toBe($expectedBalance);

                    $lastEntry = StockLedger::query()
                        ->where('product_id', $productId)
                        ->where('stock_point_id', $point->id)
                        ->orderBy('id')
                        ->get()
                        ->last();

                    expect($lastEntry)->not->toBeNull();
                    expect((string) $lastEntry->balance_after)->toBe($expectedBalance);
                }
            });
        });
    } finally {
        negStockTeardownTenants($registry);
    }
});
