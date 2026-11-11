<?php

declare(strict_types=1);

use App\Exceptions\ImmutableLedgerException;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 9.12 — Property 9 (transaction-ledger portion): Append-only ledgers
| reject mutation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 9: Append-only ledgers
| reject mutation (transaction-ledger portion)
| Validates: Requirements 25.3
|
| For ANY existing `transaction_ledger` entry, every update and delete attempt
| is rejected (App\Exceptions\ImmutableLedgerException) and the stored row
| remains byte-for-byte unchanged.
|
| Inputs are generated with innmind/black-box (NOT hand-rolled): varied ledger
| entry field values — entry type, signed amount, balance_after, reference, and
| actor id — are produced by black-box Sets and driven through a minimum of 100
| generated iterations via `->take(100)->enumerate()`. Each generated row
| references a real seeded Buyer (a Distributor) so the morph owner columns
| (buyer_type, buyer_id) point at a genuine record; `payment_id` is left null
| (purchase/return entries carry no payment and the FK is otherwise unresolved),
| which is irrelevant to the append-only guard being exercised. That guard lives
| in the TransactionLedger model persistence hooks and is independent of the
| owner/payment values.
|
| Append-only enforcement lives in the model persistence hooks
| (TransactionLedger `updating`/`deleting`), which require a real row on a real
| connection, so the property is DB-GATED: it provisions an isolated tenant
| database (stancl tenancy needs CREATE DATABASE) and skips gracefully when
| MySQL is unreachable, mirroring the existing audit/stock-ledger suites. It is
| correct against real MySQL. This mirrors the stock-ledger-portion test (task
| 7.14, AppendOnlyStockLedgerPropertyTest) and the audit-portion test (task
| 1.11, AppendOnlyAuditPropertyTest) one-to-one in style and structure.
|
*/

/**
 * Minimum number of generated iterations required by the plan.
 */
const APPEND_ONLY_TXN_LEDGER_ITERATIONS = 100;

/**
 * A black-box Set of varied transaction_ledger field maps.
 *
 * Each generated value is an associative array of column => value covering the
 * columns the append-only guard does not constrain. The owner columns
 * (`buyer_type`, `buyer_id`) are filled in by the test from the seeded Buyer so
 * referential meaning holds, while the Sets explore the remaining input space
 * intelligently:
 *   - `entry_type`: the enumerated transaction entry types (R25.1, R22.3) plus
 *     free-form ASCII strings (the column is a plain string);
 *   - `amount`: signed DECIMAL(15,2) values spanning positive (purchases),
 *     negative (payments/returns), zero, and boundary magnitudes;
 *   - `balance_after`: the resulting running balance, likewise signed
 *     DECIMAL(15,2);
 *   - `reference`: nullable, varied printable-ASCII source reference;
 *   - `actor_id`: nullable positive bigint acting-user identifiers.
 *
 * @return Set<array<string, mixed>>
 */
function txnLedgerAppendOnlyEntrySet(): Set
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

    // Enumerated transaction entry types (R25.1, R22.3) plus free-form ASCII
    // strings (the column is a plain string so unknown types persist too).
    $entryType = Set::either(
        Set::of(
            TransactionLedger::ENTRY_PURCHASE,
            TransactionLedger::ENTRY_PAYMENT,
            TransactionLedger::ENTRY_RETURN,
            TransactionLedger::ENTRY_CANCELLATION,
        ),
        $asciiString(1, 24)
            ->filter(static fn (string $s): bool => trim($s) !== ''),
    );

    // Signed DECIMAL(15,2) magnitudes, formatted to two decimal places so the
    // stored string matches the column precision exactly.
    $decimal = Set::either(
        Set::integers()->between(-99_999_999, 99_999_999)
            ->map(static fn (int $cents): string => number_format($cents / 100, 2, '.', '')),
        Set::of('0.00', '-0.01', '0.01', '100.50', '-30.25', '9999999999999.99'),
    );

    // Positive bigint actor ids, or null for system/automated movements.
    $actorId = Set::either(
        Set::of(null),
        Set::integers()->between(1, PHP_INT_MAX),
    );

    // Nullable, varied ASCII source reference (invoice/order/payment number).
    $nullableReference = Set::either(
        Set::of(null, '', 'INV-1', 'ORD-42', 'PAY-7', 'return #3'),
        $asciiString(0, 48),
    );

    return Set::compose(
        static fn (string $type, string $amount, string $balance, $reference, $actor): array => [
            'entry_type' => $type,
            'amount' => $amount,
            'balance_after' => $balance,
            'payment_id' => null,
            'reference' => $reference,
            'actor_id' => $actor,
        ],
        $entryType,
        $decimal,
        $decimal,
        $nullableReference,
        $actorId,
    )->toSet();
}

/**
 * Seed one Distributor Buyer inside the active tenant database and return it.
 * Ledger rows reference a real Buyer through the morph owner columns.
 */
function txnLedgerAppendOnlySeedBuyer(): Distributor
{
    $company = Company::create(['name' => 'Acme AppendOnly']);

    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist AppendOnly',
        'contact_email' => 'append-only-buyer@example.test',
    ]);
}

/**
 * Append one transaction_ledger entry directly (append is always permitted —
 * only the subsequent update/delete is guarded), returning the fresh stored
 * model.
 */
function txnLedgerAppendOnlyInsertEntry(array $attributes): TransactionLedger
{
    $entry = new TransactionLedger;
    $entry->forceFill($attributes);
    $entry->save();

    return $entry->fresh();
}

it('rejects update and delete of every existing transaction_ledger entry and leaves it unchanged (Property 9 transaction-ledger portion, R25.3)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $suffix = strtolower(bin2hex(random_bytes(4)));

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'txnappend'.$suffix,
            'subdomain' => 'txnappend-'.$suffix,
        ]);
        $tenant->domains()->create(['domain' => 'txnappend-'.$suffix.'.example.test']);
        $tenants[] = $tenant;

        $tenant->run(function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);

            // Seed the Buyer owner once: every generated ledger row references
            // this Distributor through its morph owner columns while the
            // append-only guard is exercised.
            $buyer = txnLedgerAppendOnlySeedBuyer();

            $ownerColumns = [
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => $buyer->getKey(),
            ];

            $checked = 0;

            foreach (txnLedgerAppendOnlyEntrySet()->take(APPEND_ONLY_TXN_LEDGER_ITERATIONS)->enumerate() as $attributes) {
                // Append is permitted: the first insert must succeed.
                $entry = txnLedgerAppendOnlyInsertEntry($ownerColumns + $attributes);
                $key = $entry->getKey();

                // Snapshot the stored row straight from the tenant database.
                $before = TransactionLedger::query()->find($key)->getAttributes();

                // 1. Every update attempt is rejected.
                expect(fn () => $entry->update(['entry_type' => 'tampered-'.$checked]))
                    ->toThrow(ImmutableLedgerException::class);

                // A raw save() (e.g. changing a different column) is also rejected.
                $entry->reference = 'tampered-reference';
                expect(fn () => $entry->save())
                    ->toThrow(ImmutableLedgerException::class);

                // 2. Every delete attempt is rejected.
                expect(fn () => $entry->delete())
                    ->toThrow(ImmutableLedgerException::class);

                // 3. The stored entry still exists and is byte-for-byte unchanged.
                $reloaded = TransactionLedger::query()->find($key);
                expect($reloaded)->not->toBeNull()
                    ->and($reloaded->getAttributes())->toEqual($before);

                $checked++;
            }

            // Guard: the generator really produced the required iterations.
            expect($checked)->toBeGreaterThanOrEqual(APPEND_ONLY_TXN_LEDGER_ITERATIONS);
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
