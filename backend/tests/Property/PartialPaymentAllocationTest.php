<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use App\Services\AccountLedgerService;
use App\Services\PaymentService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Innmind\BlackBox\Set;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Property 26: Partial payment allocation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 26: Partial payment
| allocation
|
| Validates: Requirements 24.3
|
| Design reference: "Payments & Accounts" / Property 26.
|   - R24.3  THE Platform SHALL support partial Payments and allocation of a
|            Payment to one or more invoices.
|
| Statement. *For any* generated payment amount and allocation split across one
| or more invoices:
|   (a) WHEN the sum of the allocation amounts is <= the payment amount, the
|       payment records with its allocations persisted and each targeted
|       invoice's outstanding amount (invoice.amount - sum(allocations for that
|       invoice)) is reduced by EXACTLY its allocated amount; and
|   (b) WHEN the sum of the allocation amounts EXCEEDS the payment amount, the
|       recording is rejected (UnprocessableEntityHttpException / HTTP 422) and
|       NOTHING persists (no payment, no allocations, no ledger entry) — the
|       whole record() call is all-or-nothing.
|
| Strategy. We provision ONE real tenant database per proof and seed a single
| Company (seller) + Distributor (buyer) inside it. BlackBox then generates
| (default 100+ scenarii) a non-empty list of per-invoice allocation slices —
| each an exact DECIMAL(15,2) positive amount — plus a payment amount, chosen so
| that BOTH branches (sum within / sum exceeds the amount) are exercised across
| iterations. We seed one fresh invoice per slice (so each invoice starts with a
| known outstanding = its amount), fold an INDEPENDENT exact-decimal expected
| sum of allocations, invoke PaymentService::record, and assert the correct
| branch: persisted-and-reduced on (a), rejected-and-empty on (b).
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager. When MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance (live central connection).
*/

/**
 * Provision + migrate an isolated tenant for the partial-allocation proof.
 *
 * @param  array<int, Tenant>  $registry
 */
function payAllocProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'palc'.$suffix,
        'subdomain' => 'palc-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'palc-'.$suffix.'.example.test']);
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
 * @param  array<int, Tenant>  $registry
 */
function payAllocTeardownTenants(array $registry): void
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
 * Build a PaymentService wired to real collaborators.
 */
function payAllocMakeService(): PaymentService
{
    return new PaymentService(
        new AccountLedgerService,
        new WhiteLabelService(new AuditRecorder),
    );
}

/**
 * Seed a Distributor buyer inside the active tenant database.
 */
function payAllocSeedBuyer(): Distributor
{
    $company = Company::firstOrCreate(['name' => 'Acme Alloc']);

    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist Alloc',
        'contact_email' => 'alloc-buyer@example.test',
    ]);
}

/**
 * Seed one invoice row (with a known outstanding == its amount) inside the
 * active tenant database so an allocation references a real invoice.
 */
function payAllocSeedInvoice(Company $seller, int $number, string $amount): int
{
    $now = now();

    return (int) DB::table('invoices')->insertGetId([
        'order_id' => $number, // unique synthetic id (no orders table in this proof)
        'seller_type' => $seller->getMorphClass(),
        'seller_id' => $seller->getKey(),
        'series' => 'ALLOC',
        'number' => $number,
        'amount' => $amount,
        'overdue' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/**
 * The outstanding amount for an invoice: its amount minus the exact sum of all
 * allocations currently recorded against it (never float).
 */
function payAllocInvoiceOutstanding(int $invoiceId): string
{
    $amount = (string) DB::table('invoices')->where('id', $invoiceId)->value('amount');

    $allocated = '0.00';
    foreach (DB::table('payment_allocations')->where('invoice_id', $invoiceId)->pluck('amount') as $a) {
        $allocated = payAllocExactAdd($allocated, (string) $a);
    }

    return payAllocExactAdd($amount, '-'.$allocated);
}

/**
 * Add two DECIMAL(15,2) decimal strings exactly, mirroring the service's own
 * fixed-scale arithmetic so expectations are computed with the SAME exactness
 * guarantee (never via float) the invariant promises.
 */
function payAllocExactAdd(string $a, string $b): string
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

/** Compare two DECIMAL(15,2) decimal strings exactly: -1 | 0 | 1. */
function payAllocExactCompare(string $a, string $b): int
{
    if (function_exists('bccomp')) {
        return bccomp($a, $b, 2);
    }

    $diff = payAllocExactAdd($a, '-'.$b);

    if ($diff === '0.00') {
        return 0;
    }

    return str_starts_with($diff, '-') ? -1 : 1;
}

/** Format a (whole, frac) pair into a DECIMAL(15,2) decimal string. */
function payAllocDecimal(int $whole, int $frac): string
{
    return $whole.'.'.str_pad((string) $frac, 2, '0', STR_PAD_LEFT);
}

it('persists allocations and reduces each invoice outstanding by exactly its allocated amount when the sum is within the payment amount, and rejects all-or-nothing when the sum exceeds it (R24.3)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = payAllocProvisionTenant($registry);

        $tenant->run(function () {
            Mail::fake();

            $buyer = payAllocSeedBuyer();
            $seller = Company::firstOrFail();
            $service = payAllocMakeService();

            static::forAll(
                // A non-empty list of allocation slices. Each slice is an exact
                // positive DECIMAL(15,2) amount built from a (whole, frac) pair.
                // One invoice is seeded per slice, so the payment is allocated
                // across one OR MORE invoices.
                Set::sequence(
                    Set::compose(
                        static fn (int $whole, int $frac): string => payAllocDecimal($whole, $frac),
                        Set::integers()->between(1, 5000),
                        Set::integers()->between(0, 99),
                    ),
                )->between(1, 6),
                // A "slack" amount (>= 0) added to the sum of allocations to form
                // the payment amount in the WITHIN branch; also reused to build an
                // EXCEEDS payment amount by subtracting a positive shortfall. Mixed
                // across iterations so BOTH branches are exercised.
                Set::integers()->between(0, 2000),
                // Fractional slack component.
                Set::integers()->between(0, 99),
                // A branch selector: true -> within (sum <= amount), false -> exceeds.
                Set::of(true, false),
                // A positive shortfall (in cents) used to drop the payment amount
                // below the sum of allocations in the EXCEEDS branch.
                Set::integers()->between(1, 500),
            )->then(function (
                array $slices,
                int $slackWhole,
                int $slackFrac,
                bool $within,
                int $shortfallCents,
            ) use ($buyer, $seller, $service) {
                // Isolate each generated scenario: a clean slate so persistence /
                // non-persistence assertions are self-contained per iteration.
                DB::table('payment_allocations')->delete();
                DB::table('payments')->delete();
                DB::table('transaction_ledger')->delete();
                DB::table('invoices')->delete();

                // Seed one fresh invoice per slice. Each invoice's amount is large
                // enough to fully absorb its allocation, so outstanding starts at a
                // clean, known value and the allocation never over-pays an invoice.
                $allocations = [];
                $invoiceIds = [];
                $sum = '0.00';

                foreach ($slices as $i => $sliceAmount) {
                    // Invoice amount == allocation + a 100.00 cushion, so the
                    // invoice outstanding is well-defined and positive before pay.
                    $invoiceAmount = payAllocExactAdd($sliceAmount, '100.00');
                    $invoiceId = payAllocSeedInvoice($seller, $i + 1, $invoiceAmount);

                    $invoiceIds[] = $invoiceId;
                    $allocations[] = ['invoice_id' => $invoiceId, 'amount' => $sliceAmount];
                    $sum = payAllocExactAdd($sum, $sliceAmount);
                }

                // Record each invoice's outstanding BEFORE the payment.
                $outstandingBefore = [];
                foreach ($invoiceIds as $invoiceId) {
                    $outstandingBefore[$invoiceId] = payAllocInvoiceOutstanding($invoiceId);
                }

                $slack = payAllocDecimal($slackWhole, $slackFrac);

                if ($within) {
                    // Branch (a): payment amount = sum + slack (>= sum), so the
                    // sum of allocations never exceeds the payment amount.
                    $amount = payAllocExactAdd($sum, $slack);

                    $payment = $service->record($buyer, [
                        'amount' => $amount,
                        'method' => 'bank_transfer',
                        'reference' => 'ALLOC-OK',
                        'allocations' => $allocations,
                    ]);

                    // The payment and exactly one allocation per slice persist.
                    expect($payment)->toBeInstanceOf(Payment::class);
                    expect(Payment::count())->toBe(1);

                    $persisted = PaymentAllocation::where('payment_id', $payment->getKey())
                        ->orderBy('invoice_id')->get();
                    expect($persisted)->toHaveCount(count($allocations));

                    // Each targeted invoice's outstanding dropped by EXACTLY its
                    // allocated amount (exact decimal, never float).
                    foreach ($allocations as $allocation) {
                        $invoiceId = $allocation['invoice_id'];
                        $expected = payAllocExactAdd(
                            $outstandingBefore[$invoiceId],
                            '-'.$allocation['amount'],
                        );
                        expect(payAllocInvoiceOutstanding($invoiceId))->toBe($expected);
                    }

                    // Sum of persisted allocations never exceeds the payment amount.
                    $persistedSum = '0.00';
                    foreach ($persisted as $row) {
                        $persistedSum = payAllocExactAdd($persistedSum, (string) $row->amount);
                    }
                    expect(payAllocExactCompare($persistedSum, (string) $payment->amount))
                        ->toBeLessThanOrEqual(0);
                } else {
                    // Branch (b): payment amount = sum - shortfall (strictly below
                    // the sum of allocations), so the ceiling is violated. We clamp
                    // the amount to stay strictly positive; if the sum is too small
                    // to drop below while remaining positive, nudge the sum view by
                    // ensuring a positive amount and a positive shortfall.
                    $shortfall = payAllocDecimal(intdiv($shortfallCents, 100), $shortfallCents % 100);
                    $amount = payAllocExactAdd($sum, '-'.$shortfall);

                    // Keep the payment amount strictly positive (record() rejects
                    // non-positive amounts for a different reason). If subtracting
                    // the shortfall would make it <= 0, fall back to a tiny positive
                    // amount that is still guaranteed below the sum, provided the
                    // sum itself exceeds it.
                    if (payAllocExactCompare($amount, '0.00') <= 0) {
                        $amount = '0.01';
                    }

                    // Only assert the rejection when the sum genuinely exceeds the
                    // (positive) payment amount. If the generated sum equals the
                    // clamped amount (degenerate tiny case), skip to avoid a
                    // false branch — the WITHIN branch already covers equality.
                    if (payAllocExactCompare($sum, $amount) <= 0) {
                        return;
                    }

                    $call = fn () => $service->record($buyer, [
                        'amount' => $amount,
                        'method' => 'cash',
                        'reference' => 'ALLOC-OVER',
                        'allocations' => $allocations,
                    ]);

                    expect($call)->toThrow(UnprocessableEntityHttpException::class);

                    // All-or-nothing: nothing persisted when the ceiling is violated.
                    expect(Payment::count())->toBe(0);
                    expect(PaymentAllocation::count())->toBe(0);
                    expect(TransactionLedger::count())->toBe(0);

                    // Each invoice's outstanding is UNCHANGED (no allocation applied).
                    foreach ($invoiceIds as $invoiceId) {
                        expect(payAllocInvoiceOutstanding($invoiceId))
                            ->toBe($outstandingBefore[$invoiceId]);
                    }
                }
            });
        });
    } finally {
        payAllocTeardownTenants($registry);
    }
});
