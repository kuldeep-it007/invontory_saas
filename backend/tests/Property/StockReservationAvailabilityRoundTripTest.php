<?php

declare(strict_types=1);

use App\Exceptions\InsufficientAvailableStockException;
use App\Models\Company;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Tenant;
use App\Services\StockReservationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 15.7 — Property 13: Stock reservation availability and round-trip
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 13: Stock reservation
| availability and round-trip.
|
| **Validates: Requirements 23.4, 23.5**
|
| Property statement (design.md §Property 13):
|   For any set of active reservations against a stock row, available quantity
|   equals on-hand minus the sum of active reservations and the reserved
|   quantity is unavailable to other orders; reserving then cancelling/expiring
|   restores available quantity to its prior value.
|
| The requirements exercised:
|   - R23.4  A reservation BLOCKS a quantity of a stock row WITHOUT moving the
|            owner's on-hand quantity: available = on-hand − Σ(active
|            reservations), and the reserved quantity is thereby unavailable to
|            other orders (a request that would exceed available is rejected).
|   - R23.5  Cancelling (release) or expiring a reservation returns its blocked
|            quantity to available, restoring the prior available value
|            (round-trip).
|
| Universal invariants are generated and asserted per scenario by
| innmind/black-box (NOT hand-rolled) via the shared static::forAll(), which
| draws the mandated minimum of 100 generated scenarii per proof and shrinks any
| counterexample to a minimal failing input:
|
|   availability — for a generated on-hand quantity and a generated sequence of
|       reservation amounts that together never exceed on-hand, reserving each
|       amount leaves the on-hand `quantity` UNCHANGED (a reservation only
|       blocks — R23.4) while available drops to exactly on-hand − Σ(active
|       reservations). Reserving one more than the currently-available amount is
|       rejected with a 422 and persists nothing (the reserved quantity is
|       unavailable to the would-be other order — R23.4).
|
|   round-trip — releasing (cancel) every reservation one-by-one restores
|       available back through each intermediate value to the original on-hand
|       quantity, and expiring a reservation (its scheduled lapse swept by
|       releaseExpired()) likewise returns its blocked quantity to available —
|       available ends exactly at its prior value (R23.5).
|
| DB-GATED. Provisioning a real per-tenant MySQL database and exercising real
| FOR UPDATE reservation transactions needs a reachable MySQL server with CREATE
| DATABASE privileges and the stancl/tenancy MySQL manager; when MySQL is
| unreachable the proof skips gracefully via skip_without_mysql('central'),
| mirroring tests/Feature/StockReservationServiceTest.php. Every mutation runs
| inside $tenant->run() so the tenant default connection is active.
|
| NOTE on helper naming: Pest loads every Property test file together, so each
| top-level helper here is prefixed `resvProp...` to avoid "cannot redeclare
| function" collisions with sibling files.
*/

/**
 * Provision + migrate an isolated tenant for the reservation proof and register
 * it for teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function resvPropProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'rsvp'.$suffix,
        'subdomain' => 'rsvp-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'rsvp-'.$suffix.'.example.test']);
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
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param  array<int, Tenant>  $registry
 */
function resvPropTeardownTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 * The stock_reservations → stock → product FK chain is enforced, so a real
 * product row is required for every stock row we reserve against. Named
 * distinctly from sibling helpers (seedReservationProduct/seedLedgerProduct…)
 * to avoid redeclaration collisions.
 */
function resvPropSeedProduct(string $sku): int
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
 * Seed one Company-owned stock row at the given on-hand quantity and return it.
 */
function resvPropSeedStock(Company $company, int $productId, string $quantity): Stock
{
    $pointId = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0])->id;

    $stock = new Stock;
    $stock->forceFill([
        'stockable_type' => $company->getMorphClass(),
        'stockable_id' => $company->getKey(),
        'product_id' => $productId,
        'stock_point_id' => $pointId,
        'quantity' => $quantity,
    ])->save();

    return $stock->refresh();
}

/**
 * Normalize an integer unit count to a DECIMAL(15,3) string, e.g. 7 => "7.000".
 */
function resvPropQty(int $units): string
{
    return number_format($units, 3, '.', '');
}

/**
 * Add two DECIMAL(15,3) decimal strings exactly (never via float) so expected
 * availability totals carry the same exactness guarantee the invariant promises
 * (R13.3). `$b` may be negative to subtract.
 */
function resvPropAdd(string $a, string $b): string
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

it('keeps available = on-hand − Σ(active reservations) without moving owned quantity, rejects over-available, and restores available on release/expiry (R23.4, R23.5)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = resvPropProvisionTenant($registry);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Reservation Co']);
            $productId = resvPropSeedProduct('RSVP-P1');

            $service = new StockReservationService;

            static::forAll(
                // On-hand quantity to seed (whole units kept as DECIMAL(15,3)).
                Set::integers()->between(1, 100_000),
                // A sequence of 1..6 reservation amounts, each a positive unit
                // count. Clamped per-scenario below so the running sum never
                // exceeds on-hand (an over-available request is proven to be
                // rejected separately, so here we exercise the accepted path).
                Set::sequence(Set::integers()->between(1, 100_000))->between(1, 6),
                // Whether this scenario ends reservations by releasing them
                // (order cancelled) or by expiring them (scheduled lapse swept
                // by releaseExpired()). Both must restore available (R23.5).
                Set::of('release', 'expire'),
            )->then(function (int $onHandUnits, array $amountUnits, string $endMode) use (
                $service,
                $company,
                $productId,
            ) {
                // Isolate each generated scenario: a fresh stock row + no
                // reservations, so the before/after availability is
                // self-contained per iteration.
                DB::connection('tenant')->table('stock_reservations')->delete();
                DB::connection('tenant')->table('stock')->delete();
                DB::connection('tenant')->table('stock_points')->delete();

                $onHand = resvPropQty($onHandUnits);
                $stock = resvPropSeedStock($company, $productId, $onHand);

                // Clamp each generated amount so the running total of ACTIVE
                // reservations never exceeds on-hand — these are the requests
                // that must be ACCEPTED. Anything left over (0 after clamping)
                // is dropped so we only reserve genuinely-available quantity.
                $remainingUnits = $onHandUnits;
                $acceptedUnits = [];
                foreach ($amountUnits as $want) {
                    if ($remainingUnits <= 0) {
                        break;
                    }
                    $take = min($want, $remainingUnits);
                    $acceptedUnits[] = $take;
                    $remainingUnits -= $take;
                }

                // Baseline: with no reservations, available == on-hand (R23.4).
                expect($service->availableQuantity($stock->refresh()))->toBe($onHand);

                // ---- availability invariant: reserve each accepted amount ----
                // available must track on-hand − Σ(active) exactly, and on-hand
                // must NOT move (a reservation only blocks — R23.4).
                $reservations = [];
                $reservedSoFarUnits = 0;
                foreach ($acceptedUnits as $units) {
                    $expiresAt = $endMode === 'expire' ? now()->addDay() : null;
                    $reservations[] = $service->reserve(
                        $stock,
                        resvPropQty($units),
                        null,
                        ['expires_at' => $expiresAt],
                    );
                    $reservedSoFarUnits += $units;

                    // On-hand owned quantity is UNCHANGED by a reservation.
                    expect((string) $stock->refresh()->quantity)->toBe($onHand);

                    // available == on-hand − Σ(active reservations) exactly.
                    $expectedAvailable = resvPropAdd($onHand, '-'.resvPropQty($reservedSoFarUnits));
                    expect($service->availableQuantity($stock->refresh()))->toBe($expectedAvailable);
                    expect($service->reservedQuantity((int) $stock->getKey()))
                        ->toBe(resvPropQty($reservedSoFarUnits));
                }

                $availableAfterReserving = resvPropAdd($onHand, '-'.resvPropQty($reservedSoFarUnits));

                // ---- reserved quantity is unavailable to other orders (R23.4) ----
                // Requesting one more unit than is currently available is
                // rejected with a 422 and persists NOTHING (the blocked
                // quantity is genuinely unavailable). Only meaningful while some
                // quantity is actually blocked.
                if ($reservedSoFarUnits > 0) {
                    $activeBefore = StockReservation::query()->active()->count();

                    $overBy = $service->availableQuantity($stock->refresh());
                    $overRequest = resvPropAdd($overBy, '0.001'); // one tick past available

                    $threw = null;
                    try {
                        $service->reserve($stock, $overRequest);
                    } catch (InsufficientAvailableStockException $e) {
                        $threw = $e;
                    }

                    expect($threw)->not->toBeNull();
                    expect($threw->getStatusCode())->toBe(422);
                    expect($threw->available)->toBe($availableAfterReserving);
                    // All-or-nothing: the rejected request added no reservation.
                    expect(StockReservation::query()->active()->count())->toBe($activeBefore);
                    expect($service->availableQuantity($stock->refresh()))->toBe($availableAfterReserving);
                }

                // ---- round-trip: ending every reservation restores available ----
                if ($endMode === 'release') {
                    // Release one-by-one; available climbs back through each
                    // intermediate value to the original on-hand (R23.5).
                    $stillReservedUnits = $reservedSoFarUnits;
                    foreach ($reservations as $i => $reservation) {
                        $service->release($reservation);
                        $stillReservedUnits -= $acceptedUnits[$i];

                        $expected = resvPropAdd($onHand, '-'.resvPropQty($stillReservedUnits));
                        expect($service->availableQuantity($stock->refresh()))->toBe($expected);
                    }
                } else {
                    // All reservations are expired (expires_at in the past once
                    // swept). Sweep them and assert every one was released.
                    $releasedCount = $service->releaseExpired(now()->addDays(2));
                    expect($releasedCount)->toBe(count($reservations));
                }

                // Round-trip complete: available is restored EXACTLY to its
                // prior value — the full on-hand quantity, with the on-hand
                // owned quantity never having moved (R23.4, R23.5).
                expect($service->reservedQuantity((int) $stock->getKey()))->toBe('0.000');
                expect($service->availableQuantity($stock->refresh()))->toBe($onHand);
                expect((string) $stock->refresh()->quantity)->toBe($onHand);
            });
        });
    } finally {
        resvPropTeardownTenants($registry);
    }
});
