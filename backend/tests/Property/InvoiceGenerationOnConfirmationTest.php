<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 11: Invoice generation on confirmation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 11: Invoice generation on
| confirmation
|
| Validates: Requirements 21.5
|
| Design reference: "Confirmation" / Property 11.
|   - R21.5  WHEN an Order is confirmed, THE Platform SHALL generate an invoice
|            using the Seller's own invoice series synchronously within the
|            confirmation transaction before returning the confirmation
|            response.
|
| Statement. *For any* generated sequence of invoice generations across one or
| more sellers/series, {@see InvoiceService::generate()} produces EXACTLY ONE
| invoice per confirmed order (one row per distinct order_id), the numbers
| within each series are strictly increasing and contiguous (+1, starting at 1,
| never reused, never regressed), and allocation in one series is independent
| of every other series/seller (each series keeps its own running counter).
|
| Strategy. We provision ONE real tenant database per proof and seed a fixed
| pair of sellers inside it: the tenant Company (seller for distributor orders)
| and one Distributor (seller for dealer orders). BlackBox then generates
| (default 100+ scenarii) a sequence of (sellerIndex, seriesName, amount)
| generation requests. We assign each request a FRESH unique order_id (one
| invoice per order), apply it through InvoiceService::generate against the
| appropriate seller, and fold an INDEPENDENT per-series expectation of the
| next number. After the whole sequence we re-read the invoices table and
| assert: one invoice per order_id, strictly-increasing contiguous numbers
| within each series, correct seller morph per invoice, and cross-series
| independence.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager. When MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'); the assertions are written to be correct
| against a real MySQL instance.
|
| NOTE on helper naming: top-level helpers are prefixed `invoiceProp...` so they
| never collide with the InvoiceServiceTest helpers (e.g. provisionInvoiceTenant)
| that Pest loads into the same global function space.
*/

/**
 * Provision + migrate an isolated tenant for the invoice-generation proof.
 *
 * @param  array<int, Tenant>  $registry
 */
function invoicePropProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'invp'.$suffix,
        'subdomain' => 'invp-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'invp-'.$suffix.'.example.test']);
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
function invoicePropTeardownTenants(array $registry): void
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

it('generates exactly one invoice per confirmed order, with strictly increasing numbers within each series independently across sellers/series (R21.5, Property 11)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = invoicePropProvisionTenant($registry);

        $tenant->run(function () {
            // Fixed pair of sellers: the tenant Company and one Distributor.
            // Each generation request targets one of them by index.
            $company = Company::create(['name' => 'Acme Invoices']);
            $distributor = Distributor::create([
                'company_id' => $company->id,
                'name' => 'Dist One',
            ]);
            $sellers = [$company, $distributor];

            $service = new InvoiceService;

            static::forAll(
                // A non-empty sequence of invoice-generation requests. Each is a
                // tuple [sellerIndex (0=company,1=distributor), seriesName, amount].
                // Series names are drawn from a small vocabulary so collisions
                // across requests are frequent (exercising per-series counters),
                // while still spanning multiple distinct series and both sellers.
                Set::sequence(
                    Set::compose(
                        static fn (int $sellerIndex, string $series, int $rupees, int $paise): array => [
                            $sellerIndex,
                            $series,
                            $rupees.'.'.str_pad((string) $paise, 2, '0', STR_PAD_LEFT),
                        ],
                        Set::of(0, 1),
                        Set::of('COMPANY', 'DIST-1', 'FY25', 'FY26', 'EXPORT'),
                        Set::integers()->between(0, 999999),
                        Set::integers()->between(0, 99),
                    ),
                )->between(1, 10),
            )->then(function (array $requests) use ($sellers, $service) {
                // Isolate each generated scenario: start from an empty invoices
                // table so each series' running counter is self-contained per
                // iteration (strictly-increasing-from-1 is a per-scenario claim).
                Invoice::query()->getConnection()->table('invoices')->delete();

                // INDEPENDENT per-series expectation of the next number, folded
                // in the test. Each series keeps its own counter regardless of
                // which seller/order drives it.
                $expectedNextNumber = [];
                // Expected invoice facts keyed by the fresh order_id we assign.
                $expectedByOrder = [];
                // The order_ids we generated, in order, to assert one-per-order.
                $orderIds = [];

                $nextOrderId = 1000;

                foreach ($requests as [$sellerIndex, $series, $amount]) {
                    $seller = $sellers[$sellerIndex];
                    $orderId = $nextOrderId++;
                    $orderIds[] = $orderId;

                    // Independent expectation: next number for THIS series.
                    $expectedNextNumber[$series] = ($expectedNextNumber[$series] ?? 0) + 1;

                    $invoice = $service->generate($orderId, $seller, $series, $amount);

                    // The returned invoice is the single invoice for this order,
                    // carrying the series' next contiguous number (R21.5).
                    expect($invoice->order_id)->toBe($orderId);
                    expect($invoice->series)->toBe($series);
                    expect($invoice->number)->toBe($expectedNextNumber[$series]);
                    expect($invoice->seller->is($seller))->toBeTrue();
                    expect($invoice->seller_type)->toBe($seller->getMorphClass());

                    $expectedByOrder[$orderId] = [
                        'series' => $series,
                        'number' => $expectedNextNumber[$series],
                        'seller_type' => $seller->getMorphClass(),
                        'seller_id' => (int) $seller->getKey(),
                        'amount' => $amount,
                    ];
                }

                // --- Exactly one invoice per confirmed order (one per order_id).
                $all = Invoice::orderBy('id')->get();
                expect($all)->toHaveCount(count($orderIds));

                $distinctOrderIds = $all->pluck('order_id')->unique()->values();
                expect($distinctOrderIds->count())->toBe(count($orderIds));

                foreach ($expectedByOrder as $orderId => $expected) {
                    $rows = $all->where('order_id', $orderId)->values();
                    // Exactly one invoice for this order.
                    expect($rows)->toHaveCount(1);

                    $row = $rows->first();
                    expect($row->series)->toBe($expected['series']);
                    expect($row->number)->toBe($expected['number']);
                    expect($row->seller_type)->toBe($expected['seller_type']);
                    expect((int) $row->seller_id)->toBe($expected['seller_id']);
                    // DECIMAL(15,2) is stored/returned at scale 2.
                    expect((string) $row->amount)->toBe(
                        number_format((float) $expected['amount'], 2, '.', '')
                    );
                }

                // --- Strictly increasing, contiguous numbers WITHIN each series,
                //     independently per series (R21.5, Property 11).
                $bySeries = $all->groupBy('series');

                foreach ($bySeries as $series => $rows) {
                    $numbers = $rows->pluck('number')->map(fn ($n) => (int) $n)->sort()->values()->all();

                    // Starts at 1 and is strictly increasing by exactly +1 (no
                    // gaps, no duplicates, no regression) for this series alone.
                    $expectedSequence = range(1, count($numbers));
                    expect($numbers)->toBe($expectedSequence);

                    // Reinforce strict monotonic increase pairwise.
                    for ($i = 1; $i < count($numbers); $i++) {
                        expect($numbers[$i])->toBeGreaterThan($numbers[$i - 1]);
                    }

                    // Cross-check against the independently folded counter: the
                    // highest number equals how many times THIS series was used.
                    expect(count($numbers))->toBe($expectedNextNumber[$series]);
                    expect(max($numbers))->toBe($expectedNextNumber[$series]);
                }

                // --- Independence across series/sellers: series counters never
                //     bleed into one another. The sum of per-series highest
                //     numbers equals the total number of invoices generated.
                $sumPerSeriesCounts = array_sum(array_map(
                    static fn ($rows) => $rows->count(),
                    $bySeries->all(),
                ));
                expect($sumPerSeriesCounts)->toBe(count($orderIds));
            });
        });
    } finally {
        invoicePropTeardownTenants($registry);
    }
});
