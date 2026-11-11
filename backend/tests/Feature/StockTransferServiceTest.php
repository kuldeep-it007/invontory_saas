<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockPointOwnerMismatchException;
use App\Models\Company;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Tenant;
use App\Services\StockMovementService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 7.18 — Stock transfer between stock points of the same owner (R18.1)
|--------------------------------------------------------------------------
|
| Exercises StockTransferService::transfer() against a real provisioned tenant
| database. A transfer:
|   • decrements the source stock point and increments the destination by the
|     SAME quantity, writing exactly TWO stock_ledger entries (transfer_out then
|     transfer_in) tied by one shared reference, in ONE transaction (R18.1);
|   • is rejected with a 422 StockPointOwnerMismatchException when the source and
|     destination belong to DIFFERENT owners, persisting nothing (R18.1);
|   • is rejected (and persists nothing) when it would overdraw the source,
|     because the decrement routes through the single inventory authority's
|     negative-stock guard (R17.1).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock-movement suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for stock-transfer tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionTransferTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'xfer'.$suffix,
        'subdomain' => 'xfer-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'xfer-'.$suffix.'.example.test']);
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
function seedTransferProduct(string $sku = 'SKU-X'): int
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
function teardownTransferTenants(array $registry): void
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

it('decrements the source, increments the destination, and writes two ledger entries in one transaction (R18.1)', function () {
    $registry = [];
    $tenant = provisionTransferTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $src = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $dst = $company->stockPoints()->create(['name' => 'Overflow', 'sort_order' => 1]);
            $productId = seedTransferProduct('SKU-XFER-OK');

            $movements = new StockMovementService;
            $transfers = new StockTransferService($movements);

            // Seed the source with 40.
            $movements->apply([
                \App\Services\Stock\StockMovement::forOwner($company, $productId, $src->id, '40.000', 'opening'),
            ]);
            $ledgerBefore = StockLedger::count();

            $entries = $transfers->transfer(
                productId: $productId,
                sourceStockPointId: (int) $src->getKey(),
                destinationStockPointId: (int) $dst->getKey(),
                quantity: '15.000',
                context: ['actor_id' => 7, 'reason' => 'rebalance'],
            );

            // Exactly two ledger entries written: out at the source, in at the dest.
            expect($entries)->toHaveCount(2);
            expect(StockLedger::count())->toBe($ledgerBefore + 2);

            [$out, $in] = $entries;
            expect($out->movement_type)->toBe('transfer_out');
            expect((string) $out->quantity)->toBe('-15.000');
            expect((string) $out->balance_after)->toBe('25.000');
            expect($out->actor_id)->toBe(7);
            expect($out->reason)->toBe('rebalance');

            expect($in->movement_type)->toBe('transfer_in');
            expect((string) $in->quantity)->toBe('15.000');
            expect((string) $in->balance_after)->toBe('15.000');
            expect($in->actor_id)->toBe(7);

            // Both movements share one reference tying the pair together.
            expect($in->reference)->toBe($out->reference);

            // Stock relocated: source down to 25, destination up to 15.
            expect((string) Stock::where('stock_point_id', $src->id)->firstOrFail()->quantity)->toBe('25.000');
            expect((string) Stock::where('stock_point_id', $dst->id)->firstOrFail()->quantity)->toBe('15.000');
        });
    } finally {
        teardownTransferTenants($registry);
    }
});

it('rejects a cross-owner transfer with a 422 and persists nothing (R18.1)', function () {
    $registry = [];
    $tenant = provisionTransferTenant($registry);

    try {
        $tenant->run(function () {
            $companyA = Company::create(['name' => 'Acme']);
            $companyB = Company::create(['name' => 'Globex']);
            $src = $companyA->stockPoints()->create(['name' => 'A Main', 'sort_order' => 0]);
            $dst = $companyB->stockPoints()->create(['name' => 'B Main', 'sort_order' => 0]);
            $productId = seedTransferProduct('SKU-XFER-OWN');

            $movements = new StockMovementService;
            $transfers = new StockTransferService($movements);

            $movements->apply([
                \App\Services\Stock\StockMovement::forOwner($companyA, $productId, $src->id, '40.000', 'opening'),
            ]);
            $ledgerBefore = StockLedger::count();
            $srcBefore = (string) Stock::where('stock_point_id', $src->id)->firstOrFail()->quantity;

            $threw = null;
            try {
                $transfers->transfer(
                    productId: $productId,
                    sourceStockPointId: (int) $src->getKey(),
                    destinationStockPointId: (int) $dst->getKey(),
                    quantity: '5.000',
                );
            } catch (StockPointOwnerMismatchException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);

            // Nothing moved and no ledger entry written.
            expect(StockLedger::count())->toBe($ledgerBefore);
            expect((string) Stock::where('stock_point_id', $src->id)->firstOrFail()->quantity)->toBe($srcBefore);
            expect(Stock::where('stock_point_id', $dst->id)->exists())->toBeFalse();
        });
    } finally {
        teardownTransferTenants($registry);
    }
});

it('rejects a transfer that would overdraw the source and persists nothing (R18.1, R17.1)', function () {
    $registry = [];
    $tenant = provisionTransferTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $src = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $dst = $company->stockPoints()->create(['name' => 'Overflow', 'sort_order' => 1]);
            $productId = seedTransferProduct('SKU-XFER-NEG');

            $movements = new StockMovementService;
            $transfers = new StockTransferService($movements);

            $movements->apply([
                \App\Services\Stock\StockMovement::forOwner($company, $productId, $src->id, '3.000', 'opening'),
            ]);
            $ledgerBefore = StockLedger::count();

            $threw = null;
            try {
                $transfers->transfer(
                    productId: $productId,
                    sourceStockPointId: (int) $src->getKey(),
                    destinationStockPointId: (int) $dst->getKey(),
                    quantity: '10.000',
                );
            } catch (InsufficientStockException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);

            // All-or-nothing: source unchanged, destination never created, no ledger.
            expect((string) Stock::where('stock_point_id', $src->id)->firstOrFail()->quantity)->toBe('3.000');
            expect(Stock::where('stock_point_id', $dst->id)->exists())->toBeFalse();
            expect(StockLedger::count())->toBe($ledgerBefore);
        });
    } finally {
        teardownTransferTenants($registry);
    }
});
