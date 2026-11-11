<?php

declare(strict_types=1);

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
| Property 3: Ledger balance-after invariant
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 3: Ledger balance-after
| invariant
|
| Validates: Requirements 14.5, 15.1, 15.3
|
| Design reference: "Inventory Engine" / Property 3.
|   - R14.5  Applying a stock adjustment writes a Stock_Ledger entry capturing
|            the adjustment.
|   - R15.1  Any stock quantity change writes a Stock_Ledger entry recording
|            movement type, (signed) quantity, resulting balance, reason, actor,
|            timestamp, and reference.
|   - R15.3  Each Stock_Ledger entry's resulting balance equals the owner's
|            Stock quantity for that Product and Stock_Point AFTER the movement
|            is applied.
|
| Statement. *For any* generated sequence of stock movements applied to a given
| owner/product/stock_point, each resulting `stock_ledger` entry (in insertion
| order) records the signed movement delta as its `quantity` and a
| `balance_after` exactly equal to the owner's `stock.quantity` for that
| (product, stock_point) immediately after that movement, and carries the
| movement type, reason, actor, timestamp, and reference supplied for it.
|
| Strategy. We provision ONE real tenant database per proof and seed a single
| Company owner, one stock point, and one catalog product inside it. BlackBox
| then generates (default 100+ scenarii) a sequence of movements — each a
| (signed delta, movement type, reason, reference) tuple. We fold an exact
| decimal running balance in the test independently of the service, apply each
| movement through StockMovementService (with allow_negative so any arbitrary
| sequence is admissible — the balance-after invariant is what we assert, not
| the negative-stock guard), and then verify every ledger row against that
| independently-computed expectation.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager. When MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance.
*/

/**
 * Provision + migrate an isolated tenant for the balance-after proof.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionLedgerTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'ldg'.$suffix,
        'subdomain' => 'ldg-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'ldg-'.$suffix.'.example.test']);
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
 * The ledger/stock product_id FK is enforced, so a real product is required.
 */
function seedLedgerProduct(string $sku = 'SKU-LDG'): int
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
function teardownLedgerTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (Throwable $e) {
            // best-effort cleanup
        }
    }
}

/**
 * Add two DECIMAL(15,3) decimal strings exactly, mirroring the service's own
 * fixed-scale arithmetic so the expected running balance is computed with the
 * SAME exactness guarantee (never via float) that the invariant promises.
 */
function ledgerExactAdd(string $a, string $b): string
{
    if (function_exists('bcadd')) {
        return bcadd($a, $b, 3);
    }

    $toScaled = static function (string $value): int {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = substr(str_pad($parts[1] ?? '', 3, '0'), 0, 3);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    };

    $sum = $toScaled($a) + $toScaled($b);
    $negative = $sum < 0;
    $sum = abs($sum);
    $digits = str_pad((string) $sum, 4, '0', STR_PAD_LEFT);

    return ($negative ? '-' : '').substr($digits, 0, -3).'.'.substr($digits, -3);
}

it('records on each ledger entry the signed delta and a balance_after equal to the post-movement stock quantity, plus type/reason/actor/timestamp/reference (R14.5, R15.1, R15.3)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = provisionLedgerTenant($registry);

        $tenant->run(function () {
            // Fixed owner / point / product; the sequence of movements against
            // this single (owner, product, stock_point) is what we generate.
            $company = Company::create(['name' => 'Acme Ledger']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedLedgerProduct('SKU-LEDGER');

            $service = new StockMovementService;

            static::forAll(
                // A non-empty sequence of movements. Each movement is a tuple of
                //   [signedDelta(decimal string), movementType, reason|null, reference|null]
                // Deltas are bounded fixed-scale decimals that may be positive or
                // negative so arbitrary increment/decrement chains are exercised.
                Set::sequence(
                    Set::compose(
                        static fn (string $sign, int $whole, int $frac, string $type, ?string $reason, ?string $reference): array => [
                            // Signed DECIMAL(15,3) delta string, e.g. "-123.045".
                            ($sign === '-' ? '-' : '').$whole.'.'.str_pad((string) $frac, 3, '0', STR_PAD_LEFT),
                            $type,
                            $reason,
                            $reference,
                        ],
                        Set::of('+', '-'),
                        Set::integers()->between(0, 100000),
                        Set::integers()->between(0, 999),
                        // A non-empty movement type drawn from the vocabulary the
                        // engine uses across inward / adjustment / order / transfer.
                        Set::of(
                            'opening', 'received', 'adjustment_in', 'adjustment_out',
                            'sale_out', 'transfer_in', 'transfer_out', 'return_in',
                        ),
                        // reason: null OR a (possibly empty) short string.
                        Set::either(
                            Set::of(null),
                            Set::strings()->madeOf(Set::strings()->chars()->ascii())->atMost(40),
                        ),
                        // reference: null OR a short identifier-ish string.
                        Set::either(
                            Set::of(null),
                            Set::strings()->madeOf(Set::strings()->chars()->alphanumerical())->between(1, 20),
                        ),
                    ),
                )->between(1, 8),
            )->then(function (array $movements) use ($company, $productId, $point, $service) {
                // Isolate each generated scenario: start from an empty ledger and
                // a zero balance for this (owner, product, stock_point) so the
                // running-balance expectation is self-contained per iteration.
                StockLedger::query()->getConnection()->table('stock_ledger')->delete();
                Stock::query()->where('product_id', $productId)
                    ->where('stock_point_id', $point->id)->delete();

                $actorId = random_int(1, 1000);

                // Fold an INDEPENDENT exact running balance and apply each
                // movement one at a time, so each ledger entry's balance_after
                // must equal the stock quantity immediately after that movement.
                $expectedBalance = '0.000';
                $expected = [];

                foreach ($movements as [$delta, $type, $reason, $reference]) {
                    $expectedBalance = ledgerExactAdd($expectedBalance, $delta);

                    $service->apply([
                        StockMovement::forOwner($company, $productId, $point->id, $delta, $type, $reason, $reference),
                    ], ['actor_id' => $actorId, 'allow_negative' => true]);

                    // The owner's live stock quantity after THIS movement.
                    $liveQty = (string) Stock::query()
                        ->where('product_id', $productId)
                        ->where('stock_point_id', $point->id)
                        ->firstOrFail()->quantity;

                    // R15.3: resulting balance == owner's post-movement quantity.
                    expect($liveQty)->toBe($expectedBalance);

                    $expected[] = [
                        'quantity' => ledgerExactAdd($delta, '0.000'), // normalized to scale 3
                        'balance_after' => $expectedBalance,
                        'movement_type' => $type,
                        'reason' => $reason,
                        'reference' => $reference,
                    ];
                }

                // The ledger holds exactly one entry per movement, in order.
                $entries = StockLedger::orderBy('id')->get();
                expect($entries)->toHaveCount(count($movements));

                foreach ($entries as $i => $entry) {
                    // R15.1: the ledger records the SIGNED movement delta.
                    expect((string) $entry->quantity)->toBe($expected[$i]['quantity']);

                    // R15.3: balance_after == owner quantity after this movement.
                    expect((string) $entry->balance_after)->toBe($expected[$i]['balance_after']);

                    // R15.1: movement type / reason / reference captured verbatim.
                    expect($entry->movement_type)->toBe($expected[$i]['movement_type']);
                    expect($entry->reason)->toBe($expected[$i]['reason']);
                    expect($entry->reference)->toBe($expected[$i]['reference']);

                    // R15.1: actor and timestamp captured.
                    expect($entry->actor_id)->toBe($actorId);
                    expect($entry->created_at)->not->toBeNull();

                    // R15.1: the entry is attributed to the correct owner / point.
                    expect($entry->owner->is($company))->toBeTrue();
                    expect((int) $entry->stock_point_id)->toBe((int) $point->id);
                    expect((int) $entry->product_id)->toBe($productId);
                }

                // The final live balance equals the last entry's balance_after,
                // closing the chain (R15.3).
                expect((string) $entries->last()->balance_after)->toBe($expectedBalance);
            });
        });
    } finally {
        teardownLedgerTenants($registry);
    }
});
