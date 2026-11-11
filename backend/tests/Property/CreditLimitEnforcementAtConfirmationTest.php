<?php

declare(strict_types=1);

use App\Exceptions\CreditLimitExceededException;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use App\Services\AccountLedgerService;
use App\Services\CreditEnforcementService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;
use Innmind\BlackBox\Set\Provider;

/*
|--------------------------------------------------------------------------
| Property 24: Credit-limit enforcement at confirmation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 24: Credit-limit
| enforcement at confirmation
|
| Validates: Requirements 26.2
|
| Design reference: "Payments & Accounts" / Property 24.
|   - R26.2  Confirmation is blocked if and only if the buyer's projected
|            Outstanding_Balance (current outstanding O + order total T) exceeds
|            the configured credit limit L, and this check is applied ONLY at
|            confirmation time.
|
| Statement. *For any* buyer with current outstanding balance O, order total T
| and credit limit L (all exact DECIMAL(15,2) decimals), calling
| CreditEnforcementService::assertCreditWithinLimit($buyer, T) — the exact seam
| OrderConfirmationService invokes at confirmation — BLOCKS the confirmation
| (throws CreditLimitExceededException, surfaced as HTTP 422) IF AND ONLY IF
| O + T > L (strictly greater; reaching the limit exactly is permitted), and
| otherwise permits it. Because the check is evaluated only when invoked, this
| isolates the "applied only at confirmation time" guarantee: the outstanding O
| is seeded up front and T is supplied solely at the assert call.
|
| Strategy. We provision ONE real tenant database per proof and seed a single
| Company + Distributor buyer inside it. BlackBox then generates (default 100+
| scenarii) a triple (O, T, L) of bounded fixed-scale DECIMAL(15,2) decimals.
| Per scenario we reset the buyer's ledger to zero, seed exactly O via
| AccountLedgerService::appendPurchase (making O the Outstanding_Balance), set
| the buyer's payment_terms credit_limit to L, then call assertCreditWithinLimit
| with order total T and assert it throws IFF O + T > L. An INDEPENDENT exact
| decimal comparison (never float) in the test decides the expected outcome so
| the strict-inequality boundary is exercised exactly.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager. When MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance.
|
| NOTE: top-level helpers are file-uniquely named (creditLimit…) so Pest can
| load this file alongside the other property suites without redeclaration.
|
*/

/**
 * Provision + migrate an isolated tenant for the credit-limit proof.
 *
 * @param  array<int, Tenant>  $registry
 */
function creditLimitProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'crl'.$suffix,
        'subdomain' => 'crl-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'crl-'.$suffix.'.example.test']);
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
function creditLimitSeedBuyer(): Distributor
{
    $company = Company::create(['name' => 'Acme Credit']);

    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist Credit',
        'contact_email' => 'credit-buyer@example.test',
    ]);
}

/**
 * @param  array<int, Tenant>  $registry
 */
function creditLimitTeardownTenants(array $registry): void
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
 * fixed-scale arithmetic so the expected projected outstanding (O + T) is
 * computed with the SAME exactness guarantee (never via float) the invariant
 * promises.
 */
function creditLimitExactAdd(string $a, string $b): string
{
    if (function_exists('bcadd')) {
        return bcadd($a, $b, 2);
    }

    $sum = creditLimitToScaled($a) + creditLimitToScaled($b);
    $negative = $sum < 0;
    $sum = abs($sum);
    $digits = str_pad((string) $sum, 3, '0', STR_PAD_LEFT);

    return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
}

/**
 * Compare two DECIMAL(15,2) decimal strings exactly: -1 / 0 / 1.
 */
function creditLimitExactCompare(string $a, string $b): int
{
    if (function_exists('bccomp')) {
        return bccomp($a, $b, 2);
    }

    return creditLimitToScaled($a) <=> creditLimitToScaled($b);
}

/**
 * Convert a decimal string to a scaled integer (×100) for exact fallback math.
 */
function creditLimitToScaled(string $value): int
{
    $negative = str_starts_with($value, '-');
    $value = ltrim($value, '+-');
    $parts = explode('.', $value, 2);
    $whole = $parts[0] === '' ? '0' : $parts[0];
    $frac = substr(str_pad($parts[1] ?? '', 2, '0'), 0, 2);

    $scaled = (int) ($whole.$frac);

    return $negative ? -$scaled : $scaled;
}

/**
 * Build a bounded DECIMAL(15,2) decimal string Set from a whole and a cents
 * part, so varied non-negative amounts (0.00 .. 100000.99) are generated.
 */
function creditLimitDecimalSet(): Set|Provider
{
    return Set::compose(
        static fn (int $whole, int $frac): string => $whole.'.'.str_pad((string) $frac, 2, '0', STR_PAD_LEFT),
        Set::integers()->between(0, 100000),
        Set::integers()->between(0, 99),
    );
}

it('blocks confirmation iff projected outstanding O + T strictly exceeds the credit limit L, evaluated only at the confirmation seam (R26.2)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = creditLimitProvisionTenant($registry);

        $tenant->run(function () {
            // Fixed buyer; we vary (O, T, L) against this single buyer. The
            // service wired to the real ledger + audit, exactly as
            // OrderConfirmationService constructs it.
            $buyer = creditLimitSeedBuyer();
            $ledger = new AccountLedgerService;
            $service = new CreditEnforcementService($ledger, new AuditRecorder);

            static::forAll(
                creditLimitDecimalSet(), // O — current outstanding balance
                creditLimitDecimalSet(), // T — order total
                creditLimitDecimalSet(), // L — credit limit
            )->then(function (string $outstanding, string $orderTotal, string $creditLimit) use ($buyer, $ledger, $service) {
                // Isolate each scenario: start from an empty ledger (zero
                // outstanding) so O is exactly what we seed below.
                TransactionLedger::query()->getConnection()->table('transaction_ledger')->delete();

                // Seed the buyer's current Outstanding_Balance = O. A confirmed
                // purchase is the positive account movement whose running
                // balance_after becomes the Outstanding_Balance (R25.1/R25.2).
                if (creditLimitExactCompare($outstanding, '0.00') > 0) {
                    $ledger->appendPurchase($buyer, $outstanding);
                }

                // The ledger now reports exactly O.
                expect($ledger->outstandingBalance($buyer))->toBe($outstanding);

                // Set the per-buyer credit limit L in payment_terms (R26.1).
                $buyer->forceFill(['payment_terms' => ['credit_limit' => $creditLimit]])->save();
                $buyer->refresh();

                // Independent exact expectation: blocked iff O + T > L strictly.
                $projected = creditLimitExactAdd($outstanding, $orderTotal);
                $shouldBlock = creditLimitExactCompare($projected, $creditLimit) > 0;

                // The confirmation seam. OrderConfirmationService calls exactly
                // this inside the confirmation transaction with the order total.
                $call = fn () => $service->assertCreditWithinLimit($buyer, $orderTotal);

                if ($shouldBlock) {
                    // Blocked: CreditLimitExceededException (HTTP 422).
                    expect($call)->toThrow(CreditLimitExceededException::class);
                } else {
                    // Permitted: returns without throwing (reaching L exactly
                    // and staying under it are both allowed).
                    $call();
                    expect(true)->toBeTrue();
                }
            });
        });
    } finally {
        creditLimitTeardownTenants($registry);
    }
});
