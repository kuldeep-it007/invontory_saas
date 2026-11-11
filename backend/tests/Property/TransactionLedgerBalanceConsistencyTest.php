<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use App\Services\AccountLedgerService;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 8: Transaction-ledger balance consistency
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 8: Transaction-ledger
| balance consistency
|
| Validates: Requirements 22.3, 24.2, 25.1, 25.2
|
| Design reference: "Payments & Accounts" / Property 8.
|   - R22.3  A return adjusts the Buyer's account (writes a negative
|            Transaction_Ledger entry) and the Outstanding_Balance is recomputed.
|   - R24.2  A recorded Payment writes a Transaction_Ledger entry and updates the
|            Buyer's running balance.
|   - R25.1  A confirmed purchase or a Payment appends a Transaction_Ledger entry
|            recording the amount and the resulting balance-after value.
|   - R25.2  The Buyer's Outstanding_Balance is derived from the latest
|            Transaction_Ledger balance-after value.
|
| Statement. *For any* generated sequence of account-affecting events for a
| single buyer — confirmed purchases (positive), payments (negative) and returns
| (negative) of varied amounts — each `transaction_ledger` entry's
| `balance_after` (in insertion order) equals the PREVIOUS entry's
| `balance_after` plus THIS entry's signed `amount` (exact DECIMAL(15,2), never
| float), and after the whole sequence the buyer's Outstanding_Balance
| (AccountLedgerService::outstandingBalance) equals the latest `balance_after`.
|
| Strategy. We provision ONE real tenant database per proof and seed a single
| Company + Distributor buyer inside it. BlackBox then generates (default 100+
| scenarii) a non-empty sequence of movements — each a (kind, positive amount)
| tuple where kind is purchase / payment / return. We fold an INDEPENDENT exact
| decimal running balance in the test (purchase adds, payment/return subtract),
| apply each movement through AccountLedgerService, and verify every ledger row
| against that independently-computed expectation, finally asserting
| outstandingBalance() == the last entry's balance_after.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager. When MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance.
*/

/**
 * Provision + migrate an isolated tenant for the balance-consistency proof.
 *
 * @param  array<int, Tenant>  $registry
 */
function txnLedgerProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'txn'.$suffix,
        'subdomain' => 'txn-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'txn-'.$suffix.'.example.test']);
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
 * Seed one Distributor buyer inside the active tenant database.
 */
function txnLedgerSeedBuyer(): Distributor
{
    $company = Company::create(['name' => 'Acme Txn']);

    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist Txn',
        'contact_email' => 'txn-buyer@example.test',
    ]);
}

/**
 * @param  array<int, Tenant>  $registry
 */
function txnLedgerTeardownTenants(array $registry): void
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
 * Add two DECIMAL(15,2) decimal strings exactly, mirroring the service's own
 * fixed-scale arithmetic so the expected running balance is computed with the
 * SAME exactness guarantee (never via float) that the invariant promises.
 */
function txnLedgerExactAdd(string $a, string $b): string
{
    if (function_exists('bcadd')) {
        return bcadd($a, $b, 2);
    }

    $toScaled = static function (string $value): int {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = substr(str_pad($parts[1] ?? '', 2, '0'), 0, 2);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    };

    $sum = $toScaled($a) + $toScaled($b);
    $negative = $sum < 0;
    $sum = abs($sum);
    $digits = str_pad((string) $sum, 3, '0', STR_PAD_LEFT);

    return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
}

it('chains each transaction_ledger entry balance_after as previous + signed amount and derives Outstanding_Balance from the latest entry (R22.3, R24.2, R25.1, R25.2)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = txnLedgerProvisionTenant($registry);

        $tenant->run(function () {
            // Fixed buyer; the sequence of account movements against this single
            // buyer is what we generate.
            $buyer = txnLedgerSeedBuyer();
            $service = new AccountLedgerService;

            static::forAll(
                // A non-empty sequence of account movements. Each movement is a
                // tuple of [kind, positiveAmount(decimal string)] where kind is
                // one of purchase (+), payment (-) or return (-). Amounts are
                // bounded fixed-scale DECIMAL(15,2) decimals so varied-amount
                // purchase / payment / return chains are exercised.
                Set::sequence(
                    Set::compose(
                        static fn (string $kind, int $whole, int $frac): array => [
                            $kind,
                            $whole.'.'.str_pad((string) $frac, 2, '0', STR_PAD_LEFT),
                        ],
                        Set::of('purchase', 'payment', 'return'),
                        Set::integers()->between(0, 100000),
                        Set::integers()->between(0, 99),
                    ),
                )->between(1, 8),
            )->then(function (array $movements) use ($buyer, $service) {
                // Isolate each generated scenario: start from an empty ledger and
                // a zero balance for this buyer so the running-balance
                // expectation is self-contained per iteration.
                TransactionLedger::query()->getConnection()->table('transaction_ledger')->delete();

                // Fold an INDEPENDENT exact running balance and apply each
                // movement one at a time: a purchase ADDS its amount (positive
                // signed ledger amount), a payment and a return each SUBTRACT it
                // (negative signed ledger amount).
                $expectedBalance = '0.00';
                $expected = [];

                foreach ($movements as [$kind, $amount]) {
                    $signed = match ($kind) {
                        'purchase' => txnLedgerExactAdd($amount, '0.00'),      // +amount
                        default => txnLedgerExactAdd('0.00', '-'.$amount),     // -amount
                    };

                    $expectedBalance = txnLedgerExactAdd($expectedBalance, $signed);

                    $entry = match ($kind) {
                        'purchase' => $service->appendPurchase($buyer, $amount),
                        'payment' => $service->appendPayment($buyer, $amount),
                        'return' => $service->appendReturn($buyer, $amount),
                    };

                    $expected[] = [
                        'entry_type' => $kind,
                        'amount' => $signed,
                        'balance_after' => $expectedBalance,
                    ];

                    // The appended entry carries the signed amount and the
                    // running balance-after it just produced (R25.1).
                    expect((string) $entry->amount)->toBe($signed);
                    expect((string) $entry->balance_after)->toBe($expectedBalance);
                }

                // The ledger holds exactly one entry per movement, in order.
                $entries = TransactionLedger::orderBy('id')->get();
                expect($entries)->toHaveCount(count($movements));

                // Walk the persisted chain and assert the balance invariant
                // directly: each entry's balance_after == the PREVIOUS entry's
                // balance_after + this entry's signed amount (R24.2, R25.1).
                $previousBalance = '0.00';

                foreach ($entries as $i => $entry) {
                    expect($entry->entry_type)->toBe($expected[$i]['entry_type']);
                    expect((string) $entry->amount)->toBe($expected[$i]['amount']);

                    $chained = txnLedgerExactAdd($previousBalance, (string) $entry->amount);
                    expect((string) $entry->balance_after)->toBe($chained);
                    expect((string) $entry->balance_after)->toBe($expected[$i]['balance_after']);

                    $previousBalance = (string) $entry->balance_after;
                }

                // R25.2: Outstanding_Balance equals the latest balance_after.
                expect($service->outstandingBalance($buyer))->toBe((string) $entries->last()->balance_after);
                expect($service->outstandingBalance($buyer))->toBe($expectedBalance);
            });
        });
    } finally {
        txnLedgerTeardownTenants($registry);
    }
});
