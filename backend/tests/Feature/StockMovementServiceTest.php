<?php

declare(strict_types=1);

use App\Exceptions\ImmutableLedgerException;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Task 7.12 — StockMovementService core (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the single authority for stock changes against a real provisioned
| tenant database:
|   • atomic FOR UPDATE application of a movement batch in one transaction
|     (R16.4, R16.5, R35.1, R35.3);
|   • one immutable stock_ledger entry per movement with balance_after equal to
|     the post-movement stock quantity, plus type/reason/actor/reference
|     (R15.1, R15.3);
|   • full roll-back on failure so no partial changes persist (R14.2, R35.2);
|   • all-or-nothing negative-stock rejection with the offenders listed (R17.1);
|   • the ledger rejects update and delete (R15.2).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock-ownership/audit suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for stock-movement tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionMovementTenant(array &$registry, bool $backordersEnabled = false): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'move'.$suffix,
        'subdomain' => 'move-'.$suffix,
        'backorders_enabled' => $backordersEnabled,
    ]);
    $tenant->domains()->create(['domain' => 'move-'.$suffix.'.example.test']);
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
 * Stock + ledger rows reference a real product because the FK is enforced once
 * the catalog (task 7.4) is migrated.
 */
function seedMovementProduct(string $sku = 'SKU-M'): int
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
function teardownMovementTenants(array $registry): void
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

it('migrates the stock_ledger table without a tenant_id discriminator', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            expect(Schema::hasTable('stock_ledger'))->toBeTrue();
            expect(Schema::hasColumns('stock_ledger', [
                'id', 'stockable_type', 'stockable_id', 'product_id', 'stock_point_id',
                'movement_type', 'quantity', 'balance_after', 'reason', 'actor_id', 'reference',
            ]))->toBeTrue();
            expect(Schema::hasColumn('stock_ledger', 'tenant_id'))->toBeFalse();
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('applies an inward movement and writes a ledger entry with balance_after', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-IN');

            $service = new StockMovementService;

            $entries = $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '10.000', 'received', 'initial receipt', 'GRN-1'),
            ], ['actor_id' => 42]);

            expect($entries)->toHaveCount(1);

            $stock = Stock::firstOrFail();
            expect((string) $stock->quantity)->toBe('10.000');

            $ledger = StockLedger::firstOrFail();
            expect((string) $ledger->quantity)->toBe('10.000');
            expect((string) $ledger->balance_after)->toBe('10.000');
            expect($ledger->movement_type)->toBe('received');
            expect($ledger->reason)->toBe('initial receipt');
            expect($ledger->reference)->toBe('GRN-1');
            expect($ledger->actor_id)->toBe(42);
            expect($ledger->owner->is($company))->toBeTrue();
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('chains balance_after exactly across a sequence of movements', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-CHAIN');

            $service = new StockMovementService;

            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '100.500', 'opening'),
            ]);
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '-30.250', 'sale_out'),
            ]);
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '5.750', 'adjustment_in'),
            ]);

            $balances = StockLedger::orderBy('id')->pluck('balance_after')->map(fn ($b) => (string) $b)->all();
            expect($balances)->toBe(['100.500', '70.250', '76.000']);

            expect((string) Stock::firstOrFail()->quantity)->toBe('76.000');
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('transfers between two stock points of the same owner in one transaction', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $src = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $dst = $company->stockPoints()->create(['name' => 'Overflow', 'sort_order' => 1]);
            $productId = seedMovementProduct('SKU-XFER');

            $service = new StockMovementService;
            // Seed source stock.
            $service->apply([
                StockMovement::forOwner($company, $productId, $src->id, '40.000', 'opening'),
            ]);

            // Transfer 15 from source to destination atomically (two movements).
            $service->apply([
                StockMovement::forOwner($company, $productId, $src->id, '-15.000', 'transfer_out', null, 'XF-1'),
                StockMovement::forOwner($company, $productId, $dst->id, '15.000', 'transfer_in', null, 'XF-1'),
            ]);

            $srcQty = Stock::where('stock_point_id', $src->id)->firstOrFail()->quantity;
            $dstQty = Stock::where('stock_point_id', $dst->id)->firstOrFail()->quantity;
            expect((string) $srcQty)->toBe('25.000');
            expect((string) $dstQty)->toBe('15.000');

            // Two ledger entries for the transfer plus one for the opening.
            expect(StockLedger::count())->toBe(3);
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('rejects a batch that would drive stock negative and persists nothing', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-NEG');

            $service = new StockMovementService;
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '5.000', 'opening'),
            ]);

            $before = (string) Stock::firstOrFail()->quantity;
            $ledgerCountBefore = StockLedger::count();

            $threw = null;
            try {
                $service->apply([
                    StockMovement::forOwner($company, $productId, $point->id, '-9.000', 'sale_out'),
                ]);
            } catch (InsufficientStockException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
            expect($threw->offenders)->toHaveCount(1);
            expect($threw->offenders[0]['product_id'])->toBe($productId);

            // All-or-nothing: nothing changed (R17.1, R35.2).
            expect((string) Stock::firstOrFail()->quantity)->toBe($before);
            expect(StockLedger::count())->toBe($ledgerCountBefore);
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('rejects a negative-driving batch when the tenant has backorders disabled (no explicit context)', function () {
    // Task 9.5: with NO `allow_negative` in the context, the guard is driven by
    // the per-tenant `backorders_enabled` flag. Disabled → whole batch rejected
    // with 422 listing the insufficient product, nothing persists (R17.1, R17.3).
    $registry = [];
    $tenant = provisionMovementTenant($registry, backordersEnabled: false);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-BO-OFF');

            $service = new StockMovementService;
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '2.000', 'opening'),
            ]);

            $before = (string) Stock::firstOrFail()->quantity;
            $ledgerCountBefore = StockLedger::count();

            $threw = null;
            try {
                // No context: the per-tenant toggle (disabled) governs the guard.
                $service->apply([
                    StockMovement::forOwner($company, $productId, $point->id, '-5.000', 'sale_out'),
                ]);
            } catch (InsufficientStockException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);
            expect($threw->offenders)->toHaveCount(1);
            expect($threw->offenders[0]['product_id'])->toBe($productId);

            // All-or-nothing: nothing changed (R17.1, R35.2).
            expect((string) Stock::firstOrFail()->quantity)->toBe($before);
            expect(StockLedger::count())->toBe($ledgerCountBefore);
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('permits a negative-driving batch and records the backordered quantity when the tenant has backorders enabled', function () {
    // Task 9.5: with NO `allow_negative` in the context and the per-tenant
    // `backorders_enabled` flag ON, confirmation proceeds and the backordered
    // quantity is recorded as a negative balance_after (R17.2).
    $registry = [];
    $tenant = provisionMovementTenant($registry, backordersEnabled: true);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-BO-ON');

            $service = new StockMovementService;
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '2.000', 'opening'),
            ]);

            // No context: the per-tenant toggle (enabled) permits going negative.
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '-5.000', 'sale_out'),
            ]);

            // Shortfall of 3 is recorded as the resulting negative quantity
            // (the backordered quantity equals |balance_after| = 3.000).
            expect((string) Stock::firstOrFail()->quantity)->toBe('-3.000');
            expect((string) StockLedger::orderBy('id')->get()->last()->balance_after)->toBe('-3.000');
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('allows stock to go negative when the context opts in (backorder hook)', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-BACKORDER');

            $service = new StockMovementService;
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '2.000', 'opening'),
            ]);

            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '-5.000', 'sale_out'),
            ], ['allow_negative' => true]);

            expect((string) Stock::firstOrFail()->quantity)->toBe('-3.000');
            expect((string) StockLedger::orderBy('id')->get()->last()->balance_after)->toBe('-3.000');
        });
    } finally {
        teardownMovementTenants($registry);
    }
});

it('rejects updates and deletes of an existing stock_ledger entry (immutable)', function () {
    $registry = [];
    $tenant = provisionMovementTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedMovementProduct('SKU-IMMUT');

            $service = new StockMovementService;
            $service->apply([
                StockMovement::forOwner($company, $productId, $point->id, '7.000', 'opening'),
            ]);

            $entry = StockLedger::firstOrFail();

            expect(function () use ($entry) {
                $entry->quantity = '999.000';
                $entry->save();
            })->toThrow(ImmutableLedgerException::class);

            expect(fn () => $entry->fresh()->delete())->toThrow(ImmutableLedgerException::class);

            // The stored entry is unchanged (R15.2).
            expect((string) StockLedger::firstOrFail()->quantity)->toBe('7.000');
        });
    } finally {
        teardownMovementTenants($registry);
    }
});
