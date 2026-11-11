<?php

declare(strict_types=1);

use App\Exceptions\OverdueBalanceExceededException;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\CreditEnforcementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 9.16 — Property 25: Overdue flagging and blocking
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 25: Overdue flagging and
| blocking
|
| Validates: Requirements 26.3, 26.4
|
| Design reference: "Payments & Accounts" / Property 25, implemented by
| App\Services\CreditEnforcementService (task 9.14).
|   - R26.3  An unpaid invoice is flagged overdue IFF its age (in whole days
|            since issue) EXCEEDS the Buyer's configured overdue hold days
|            (strictly greater — an invoice exactly at the hold-day boundary is
|            NOT yet overdue). A fully-paid invoice is never overdue.
|   - R26.4  A Buyer is blocked from placing a NEW order IFF its overdue balance
|            EXCEEDS the configured overdue balance limit (strictly greater —
|            reaching the limit exactly is allowed).
|
| Property statement. For any generated invoice age vs. hold-days and any
| generated overdue balance vs. overdue-balance-limit:
|   (a) flagging — flagOverdueInvoices() marks the invoice `overdue` IFF the
|       unpaid invoice's age in whole days > hold days; and
|   (b) blocking — assertNotOverdueBlocked() blocks a new order (throws
|       OverdueBalanceExceededException) IFF the overdue balance > the limit.
|
| Strategy. We provision ONE real tenant database per proof and seed a single
| Company (seller) and one Distributor (buyer) inside it. BlackBox then generates
| (default 100+ scenarii per proof) the scenario inputs and we assert the service
| against an INDEPENDENT oracle:
|   • Proof (a) generates (ageInDays, holdDays, fullyPaid?) and back-dates one
|     invoice's created_at by ageInDays, optionally fully allocating it, then
|     runs flagOverdueInvoices() and asserts the stored `overdue` flag equals
|     the oracle `(!fullyPaid && age > holdDays)`.
|   • Proof (b) generates (overdueBalanceAmount, limit) and seeds a single
|     already-flagged-overdue invoice whose unpaid amount equals the generated
|     overdue balance, then asserts assertNotOverdueBlocked() throws IFF
|     overdueBalance > limit.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| central MySQL server with CREATE DATABASE privileges and the stancl/tenancy
| MySQL manager. When MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance (run against the live central connection).
|
| Generation uses innmind/black-box (NOT hand-rolled) via the shared
| static::forAll(), which draws the mandated minimum of 100 generated scenarii
| per proof and shrinks any counterexample to a minimal failing input. Top-level
| helpers are prefixed `overdue...` to keep them file-unique and avoid Pest
| global-helper collisions.
*/

/**
 * Provision + migrate an isolated tenant for the overdue flagging/blocking proof.
 *
 * @param  array<int, Tenant>  $registry
 */
function overdueProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'ovd'.$suffix,
        'subdomain' => 'ovd-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'ovd-'.$suffix.'.example.test']);
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
function overdueTeardownTenants(array $registry): void
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
 * Create a buyer Distributor with the given Payment_Terms inside the active
 * tenant database and return it. The terms blob is parsed by PaymentTerms.
 *
 * @param  array<string, mixed>  $terms
 */
function overdueMakeBuyer(Company $company, array $terms): Distributor
{
    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Buyer '.bin2hex(random_bytes(3)),
        'payment_terms' => $terms,
    ]);
}

/**
 * Seed a single invoice for a buyer (via an order) inside the active tenant.
 * The invoice carries `$amount`, is back-dated by `$ageInDays`, and starts with
 * the given `$overdue` flag. Returns the persisted Invoice.
 */
function overdueSeedInvoice(
    Company $company,
    Distributor $buyer,
    string $amount,
    int $ageInDays,
    bool $overdue = false,
    ?Carbon $asOf = null
): Invoice {
    $asOf ??= Carbon::now();
    $issuedAt = (clone $asOf)->subDays($ageInDays);

    $order = Order::create([
        'buyer_type' => $buyer->getMorphClass(),
        'buyer_id' => $buyer->getKey(),
        'seller_type' => $company->getMorphClass(),
        'seller_id' => $company->getKey(),
        'status' => 'confirmed',
        'order_type' => 'standard',
        'total' => $amount,
    ]);

    // Unique per-seller series number so the (series, number) unique index holds
    // across the many invoices seeded within a single tenant across scenarii.
    $number = (int) (DB::table('invoices')->max('number') ?? 0) + 1;

    $invoice = new Invoice([
        'order_id' => $order->getKey(),
        'series' => 'OVD',
        'number' => $number,
        'amount' => $amount,
        'overdue' => $overdue,
    ]);
    $invoice->seller_type = $company->getMorphClass();
    $invoice->seller_id = $company->getKey();
    $invoice->save();

    // Back-date the issue timestamp so the invoice's AGE is exactly $ageInDays.
    // created_at is the "issued at" the service reads; timestamps are disabled
    // for this write so the back-dated value is not overwritten by `now()`.
    $invoice->timestamps = false;
    $invoice->created_at = $issuedAt;
    $invoice->updated_at = $issuedAt;
    $invoice->save();
    $invoice->timestamps = true;

    return $invoice->fresh();
}

/**
 * Fully allocate a payment against an invoice so its outstanding becomes zero
 * (a settled invoice is never overdue — R26.3).
 */
function overdueSettleInvoice(Invoice $invoice, Distributor $buyer): void
{
    $paymentId = DB::table('payments')->insertGetId([
        'buyer_type' => $buyer->getMorphClass(),
        'buyer_id' => $buyer->getKey(),
        'amount' => $invoice->amount,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('payment_allocations')->insert([
        'payment_id' => $paymentId,
        'invoice_id' => $invoice->getKey(),
        'amount' => $invoice->amount,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('flags an unpaid invoice overdue iff its age in whole days exceeds the buyer hold days (R26.3)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = overdueProvisionTenant($registry);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme Overdue Flagging']);

            /** @var CreditEnforcementService $service */
            $service = app(CreditEnforcementService::class);

            static::forAll(
                // Hold-days window: 0..60 whole days.
                Set::integers()->between(0, 60),
                // Invoice age in whole days: 0..90, straddling the hold boundary
                // on both sides (below, at, and above) across scenarii.
                Set::integers()->between(0, 90),
                // Whether the invoice has been fully settled (never overdue).
                Set::of(true, false),
            )->then(function (int $holdDays, int $ageInDays, bool $fullyPaid) use ($company, $service) {
                // Fresh state per scenario so the single buyer/invoice is the
                // only data the flagging pass sees.
                DB::table('payment_allocations')->delete();
                DB::table('payments')->delete();
                DB::table('invoices')->delete();
                DB::table('orders')->delete();
                DB::table('distributors')->delete();

                $asOf = Carbon::create(2025, 6, 15, 12, 0, 0);

                $buyer = overdueMakeBuyer($company, [
                    'overdue_hold_days' => $holdDays,
                ]);

                $invoice = overdueSeedInvoice(
                    $company,
                    $buyer,
                    '100.00',
                    $ageInDays,
                    overdue: false,
                    asOf: $asOf,
                );

                if ($fullyPaid) {
                    overdueSettleInvoice($invoice, $buyer);
                }

                // Independent oracle: a fully-paid invoice is never overdue;
                // otherwise it is overdue IFF its age strictly exceeds hold days.
                $expectedOverdue = ! $fullyPaid && ($ageInDays > $holdDays);

                // (1) The single-invoice predicate matches the oracle (R26.3).
                expect($service->isInvoiceOverdue($invoice, $holdDays, $asOf))
                    ->toBe($expectedOverdue);

                // (2) The flagging pass persists the SAME verdict on the row.
                $service->flagOverdueInvoices($buyer, $asOf);

                expect((bool) $invoice->fresh()->overdue)->toBe($expectedOverdue);

                // (3) Idempotent: a second pass changes nothing.
                expect($service->flagOverdueInvoices($buyer, $asOf))->toBe(0);
                expect((bool) $invoice->fresh()->overdue)->toBe($expectedOverdue);
            });
        });
    } finally {
        overdueTeardownTenants($registry);
    }
});

it('blocks a new order iff the buyer overdue balance exceeds the overdue balance limit (R26.4)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = overdueProvisionTenant($registry);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme Overdue Blocking']);

            /** @var CreditEnforcementService $service */
            $service = app(CreditEnforcementService::class);

            static::forAll(
                // Overdue balance (unpaid amount on flagged-overdue invoices),
                // in whole + fractional currency units: 0.00 .. ~5000.99.
                Set::integers()->between(0, 500000),
                // Overdue balance limit, independently drawn across the same
                // range so the generated pairs land below, at, and above it.
                Set::integers()->between(0, 500000),
            )->then(function (int $overdueCents, int $limitCents) use ($company, $service) {
                DB::table('payment_allocations')->delete();
                DB::table('payments')->delete();
                DB::table('invoices')->delete();
                DB::table('orders')->delete();
                DB::table('distributors')->delete();

                $overdueBalance = number_format($overdueCents / 100, 2, '.', '');
                $limit = number_format($limitCents / 100, 2, '.', '');

                $buyer = overdueMakeBuyer($company, [
                    'overdue_balance_limit' => $limit,
                ]);

                // Seed ONE invoice already flagged overdue whose full amount is
                // unpaid, so the derived overdue balance equals $overdueBalance.
                overdueSeedInvoice(
                    $company,
                    $buyer,
                    $overdueBalance,
                    ageInDays: 30,
                    overdue: true,
                );

                // Sanity: the service's derived overdue balance matches the seed.
                expect($service->overdueBalance($buyer->fresh()))->toBe($overdueBalance);

                // Independent oracle: blocked IFF overdue balance > limit.
                $expectBlocked = bccomp($overdueBalance, $limit, 2) > 0;

                $blocked = false;
                try {
                    $service->assertNotOverdueBlocked($buyer->fresh());
                } catch (OverdueBalanceExceededException $e) {
                    $blocked = true;

                    // The exception carries the exact figures compared (R26.4).
                    expect($e->overdueBalance)->toBe($overdueBalance);
                    expect($e->overdueBalanceLimit)->toBe($limit);
                }

                expect($blocked)->toBe($expectBlocked);
            });
        });
    } finally {
        overdueTeardownTenants($registry);
    }
});
