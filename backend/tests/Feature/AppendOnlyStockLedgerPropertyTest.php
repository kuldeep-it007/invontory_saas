<?php

declare(strict_types=1);

use App\Exceptions\ImmutableLedgerException;
use App\Models\Company;
use App\Models\StockLedger;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 7.14 — Property 9 (stock-ledger portion): Append-only ledgers reject
| mutation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 9: Append-only ledgers
| reject mutation (stock-ledger portion)
| Validates: Requirements 15.2
|
| For ANY existing `stock_ledger` entry, every update and delete attempt is
| rejected (App\Exceptions\ImmutableLedgerException) and the stored row remains
| byte-for-byte unchanged.
|
| Inputs are generated with innmind/black-box (NOT hand-rolled): varied ledger
| entry field values — movement type, signed quantity, balance_after, reason,
| actor id, and reference — are produced by black-box Sets and driven through a
| minimum of 100 generated iterations via `->take(100)->enumerate()`. Each
| generated row references a real seeded product and stock point so the table's
| foreign keys (product_id → products, stock_point_id → stock_points) are
| satisfied; the append-only guard being exercised lives in the StockLedger
| model persistence hooks and is independent of those FK values.
|
| Append-only enforcement lives in the model persistence hooks (StockLedger
| `updating`/`deleting`), which require a real row on a real connection, so the
| property is DB-GATED: it provisions an isolated tenant database (stancl
| tenancy needs CREATE DATABASE) and skips gracefully when MySQL is
| unreachable, mirroring the existing audit/stock suites. It is correct against
| real MySQL. This mirrors the audit-portion test (task 1.11,
| AppendOnlyAuditPropertyTest) one-to-one in style and structure.
|
*/

/**
 * Minimum number of generated iterations required by the plan.
 */
const APPEND_ONLY_STOCK_LEDGER_ITERATIONS = 100;

/**
 * A black-box Set of varied stock_ledger field maps.
 *
 * Each generated value is an associative array of column => value covering the
 * columns the append-only guard does not constrain. The FK-bearing columns
 * (`stockable_type`, `stockable_id`, `product_id`, `stock_point_id`) are filled
 * in by the test from the seeded fixtures so referential integrity holds, while
 * the Sets explore the remaining input space intelligently:
 *   - `movement_type`: the enumerated stock movement types (R14.1, R15.1) plus
 *     free-form ASCII strings (the column is a plain string);
 *   - `quantity`: signed DECIMAL(15,3) values spanning increments, decrements,
 *     zero, and boundary magnitudes;
 *   - `balance_after`: the owner's post-movement balance, likewise signed
 *     DECIMAL(15,3);
 *   - `reason`, `reference`: nullable, varied printable-ASCII context columns;
 *   - `actor_id`: nullable positive bigint acting-user identifiers.
 *
 * @return Set<array<string, mixed>>
 */
function stockLedgerEntrySet(): Set
{
    // Printable ASCII generator: the ledger string columns use the tenant
    // database's default (non-utf8mb4) charset, so constrain free-form text to
    // the printable ASCII range (0x20–0x7E). This still explores a wide, valid
    // input space for the append-only property, which concerns rejecting
    // mutation rather than multibyte storage.
    $asciiChar = Set::integers()->between(0x20, 0x7E)
        ->map(static fn (int $code): string => chr($code));
    $asciiString = static fn (int $min, int $max): Set => Set::sequence($asciiChar)
        ->between($min, $max)
        ->map(static fn (array $chars): string => implode('', $chars));

    // Enumerated movement types (R14.1, R15.1) plus free-form ASCII strings.
    $movementType = Set::either(
        Set::of(
            'opening', 'received', 'adjustment_in', 'adjustment_out',
            'sale_out', 'purchase_in', 'transfer_in', 'transfer_out',
            'reversal', 'return_in',
        ),
        $asciiString(1, 24)
            ->filter(static fn (string $s): bool => trim($s) !== ''),
    );

    // Signed DECIMAL(15,3) magnitudes, formatted to three decimal places so the
    // stored string matches the column precision exactly.
    $decimal = Set::either(
        Set::integers()->between(-999_999_999, 999_999_999)
            ->map(static fn (int $units): string => number_format($units / 1000, 3, '.', '')),
        Set::of('0.000', '-0.001', '0.001', '100.500', '-30.250', '999999999999.999'),
    );

    // Positive bigint actor ids, or null for system/automated movements.
    $actorId = Set::either(
        Set::of(null),
        Set::integers()->between(1, PHP_INT_MAX),
    );

    // Nullable, varied ASCII context columns (reason / reference).
    $nullableText = Set::either(
        Set::of(null, '', 'manual stock correction', 'GRN-1', 'XF-1', 'order #42'),
        $asciiString(0, 48),
    );

    return Set::compose(
        static fn (string $type, string $qty, string $balance, $reason, $actor, $reference): array => [
            'movement_type' => $type,
            'quantity' => $qty,
            'balance_after' => $balance,
            'reason' => $reason,
            'actor_id' => $actor,
            'reference' => $reference,
        ],
        $movementType,
        $decimal,
        $decimal,
        $nullableText,
        $actorId,
        $nullableText,
    )->toSet();
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 * Ledger rows reference a real product because the FK is enforced once the
 * catalog (task 7.4) is migrated. Mirrors the StockMovementService test fixture.
 */
function seedAppendOnlyLedgerProduct(string $sku = 'SKU-LEDGER'): int
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
 * Append one stock_ledger entry directly (append is always permitted — only the
 * subsequent update/delete is guarded), returning the fresh stored model.
 */
function insertStockLedgerEntry(array $attributes): StockLedger
{
    $entry = new StockLedger;
    $entry->forceFill($attributes);
    $entry->save();

    return $entry->fresh();
}

it('rejects update and delete of every existing stock_ledger entry and leaves it unchanged (Property 9 stock-ledger portion, R15.2)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $suffix = strtolower(bin2hex(random_bytes(4)));

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'ledger'.$suffix,
            'subdomain' => 'ledger-'.$suffix,
        ]);
        $tenant->domains()->create(['domain' => 'ledger-'.$suffix.'.example.test']);
        $tenants[] = $tenant;

        $tenant->run(function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);

            // Seed the FK parents once: a Company owner, a stock point, and a
            // product. Every generated ledger row references these so the table
            // foreign keys hold while the append-only guard is exercised.
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $productId = seedAppendOnlyLedgerProduct();

            $ownerColumns = [
                'stockable_type' => $company->getMorphClass(),
                'stockable_id' => $company->getKey(),
                'product_id' => $productId,
                'stock_point_id' => $point->id,
            ];

            $checked = 0;

            foreach (stockLedgerEntrySet()->take(APPEND_ONLY_STOCK_LEDGER_ITERATIONS)->enumerate() as $attributes) {
                // Append is permitted: the first insert must succeed.
                $entry = insertStockLedgerEntry($ownerColumns + $attributes);
                $key = $entry->getKey();

                // Snapshot the stored row straight from the tenant database.
                $before = StockLedger::query()->find($key)->getAttributes();

                // 1. Every update attempt is rejected.
                expect(fn () => $entry->update(['movement_type' => 'tampered-'.$checked]))
                    ->toThrow(ImmutableLedgerException::class);

                // A raw save() (e.g. changing a different column) is also rejected.
                $entry->reason = 'tampered-reason';
                expect(fn () => $entry->save())
                    ->toThrow(ImmutableLedgerException::class);

                // 2. Every delete attempt is rejected.
                expect(fn () => $entry->delete())
                    ->toThrow(ImmutableLedgerException::class);

                // 3. The stored entry still exists and is byte-for-byte unchanged.
                $reloaded = StockLedger::query()->find($key);
                expect($reloaded)->not->toBeNull()
                    ->and($reloaded->getAttributes())->toEqual($before);

                $checked++;
            }

            // Guard: the generator really produced the required iterations.
            expect($checked)->toBeGreaterThanOrEqual(APPEND_ONLY_STOCK_LEDGER_ITERATIONS);
        });
    } finally {
        foreach ($tenants as $t) {
            try {
                $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
            } catch (Throwable $e) {
                // best-effort teardown
            }
            $t->domains()->delete();
            $t->delete();
        }
    }
});
