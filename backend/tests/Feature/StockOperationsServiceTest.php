<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Tenant;
use App\Services\StockMovementService;
use App\Services\StockOperationsService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 7.16 — Stock inward & manual adjustments (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises StockOperationsService against a real provisioned tenant database.
| The service is what the thin StockController delegates to for the two
| user-driven, quantity-increasing/-correcting operations behind
| POST /stock/inward and POST /stock/adjust:
|   • inward() of type opening/received/adjustment_in increases on-hand Stock
|     and writes exactly one Stock_Ledger entry in one atomic transaction
|     (R14.1, R14.2, R14.5);
|   • adjust() applies a signed adjustment, writes a Stock_Ledger entry (R14.5)
|     AND records the adjustment and its reason in the Audit_Log (R14.3, R34.3);
|   • adjust() rejects a missing/empty/whitespace reason (R14.3) — the HTTP
|     surface answers 422 for the same case (R14.4).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock-movement/transfer suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for stock-operations tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionOperationsTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'ops'.$suffix,
        'subdomain' => 'ops-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'ops-'.$suffix.'.example.test']);
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
function seedOperationsProduct(string $sku = 'SKU-O'): int
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

function makeOperationsService(): StockOperationsService
{
    return new StockOperationsService(new StockMovementService, new AuditRecorder);
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownOperationsTenants(array $registry): void
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

it('records inward (received) that increases stock and writes one ledger entry (R14.1, R14.5)', function () {
    $registry = [];
    $tenant = provisionOperationsTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedOperationsProduct('SKU-IN-RCV');

            $service = makeOperationsService();

            $ledger = $service->inward([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                'quantity' => '12.500',
                'type' => 'received',
                'reason' => 'GRN receipt',
                'reference' => 'GRN-100',
            ], actorId: 9);

            expect((string) $ledger->quantity)->toBe('12.500');
            expect((string) $ledger->balance_after)->toBe('12.500');
            expect($ledger->movement_type)->toBe('received');
            expect($ledger->actor_id)->toBe(9);
            expect($ledger->reason)->toBe('GRN receipt');
            expect($ledger->reference)->toBe('GRN-100');

            expect((string) Stock::firstOrFail()->quantity)->toBe('12.500');
            expect(StockLedger::count())->toBe(1);
            expect($ledger->owner->is($company))->toBeTrue();
        });
    } finally {
        teardownOperationsTenants($registry);
    }
});

it('defaults inward type to received and ignores a sign on the quantity (R14.1)', function () {
    $registry = [];
    $tenant = provisionOperationsTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedOperationsProduct('SKU-IN-DEF');

            $service = makeOperationsService();

            $ledger = $service->inward([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                // A signed quantity must still increase stock (inward only adds).
                'quantity' => '-8.000',
            ], actorId: null);

            expect($ledger->movement_type)->toBe('received');
            expect((string) $ledger->quantity)->toBe('8.000');
            expect((string) Stock::firstOrFail()->quantity)->toBe('8.000');
        });
    } finally {
        teardownOperationsTenants($registry);
    }
});

it('rejects an inward with an unknown movement type (R14.1)', function () {
    $registry = [];
    $tenant = provisionOperationsTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedOperationsProduct('SKU-IN-BAD');

            $service = makeOperationsService();

            expect(fn () => $service->inward([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                'quantity' => '5.000',
                'type' => 'sale_out',
            ], actorId: null))->toThrow(InvalidArgumentException::class);

            // Nothing persisted on the rejected call.
            expect(Stock::count())->toBe(0);
            expect(StockLedger::count())->toBe(0);
        });
    } finally {
        teardownOperationsTenants($registry);
    }
});

it('records a manual adjustment with a ledger entry and an audit entry carrying the reason (R14.3, R14.5, R34.3)', function () {
    $registry = [];
    $tenant = provisionOperationsTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedOperationsProduct('SKU-ADJ');

            $service = makeOperationsService();

            // Seed on-hand stock so a negative adjustment is valid.
            $service->inward([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                'quantity' => '20.000',
                'type' => 'opening',
            ], actorId: 1);

            $ledgerBefore = StockLedger::count();

            $ledger = $service->adjust([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                'quantity' => '-5.000',
                'reason' => 'damaged in transit',
                'reference' => 'ADJ-7',
            ], actorId: 3);

            // A ledger entry capturing the (signed) adjustment (R14.5).
            expect(StockLedger::count())->toBe($ledgerBefore + 1);
            expect($ledger->movement_type)->toBe('adjustment_out');
            expect((string) $ledger->quantity)->toBe('-5.000');
            expect((string) $ledger->balance_after)->toBe('15.000');
            expect($ledger->actor_id)->toBe(3);
            expect($ledger->reason)->toBe('damaged in transit');

            expect((string) Stock::firstOrFail()->quantity)->toBe('15.000');

            // The adjustment and its reason are recorded in the Audit_Log (R14.3, R34.3).
            $audit = AuditLog::query()->where('event', 'stock_adjusted')->firstOrFail();
            expect($audit->reason)->toBe('damaged in transit');
            expect($audit->new_values['movement_type'] ?? null)->toBe('adjustment_out');
            expect((string) ($audit->new_values['quantity'] ?? ''))->toBe('-5.000');
            expect($audit->new_values['stock_ledger_id'] ?? null)->toBe($ledger->getKey());
        });
    } finally {
        teardownOperationsTenants($registry);
    }
});

it('applies a positive manual adjustment as adjustment_in (R14.3, R14.5)', function () {
    $registry = [];
    $tenant = provisionOperationsTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedOperationsProduct('SKU-ADJ-POS');

            $service = makeOperationsService();

            $ledger = $service->adjust([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                'quantity' => '4.250',
                'reason' => 'found stock',
            ], actorId: null);

            expect($ledger->movement_type)->toBe('adjustment_in');
            expect((string) $ledger->quantity)->toBe('4.250');
            expect((string) $ledger->balance_after)->toBe('4.250');
            expect((string) Stock::firstOrFail()->quantity)->toBe('4.250');
        });
    } finally {
        teardownOperationsTenants($registry);
    }
});

it('rejects a manual adjustment with an empty/whitespace reason and persists nothing (R14.3, R14.4)', function () {
    $registry = [];
    $tenant = provisionOperationsTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedOperationsProduct('SKU-ADJ-NOREASON');

            $service = makeOperationsService();

            expect(fn () => $service->adjust([
                'product_id' => $productId,
                'stock_point_id' => (int) $point->getKey(),
                'quantity' => '3.000',
                'reason' => '   ',
            ], actorId: null))->toThrow(InvalidArgumentException::class);

            // All-or-nothing: no stock, ledger, or audit written.
            expect(Stock::count())->toBe(0);
            expect(StockLedger::count())->toBe(0);
            expect(AuditLog::query()->where('event', 'stock_adjusted')->count())->toBe(0);
        });
    } finally {
        teardownOperationsTenants($registry);
    }
});
