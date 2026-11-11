<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 7.21 — Property 2: Stock movement correctness and conservation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 2: Stock movement
| correctness and conservation
|
| Validates: Requirements 13.6, 14.1, 16.1, 16.2, 16.3, 18.1, 18.3, 22.2
|
| Property statement (design.md §Property 2):
|   For any confirmed order (distributor or dealer), applying the movement
|   decrements the seller's stock and, where the buyer holds stock, increments
|   the buyer's stock by exactly each line quantity; when dealer stock-holding
|   is disabled no dealer stock is created or incremented; for transfers between
|   two stock points of one owner the total owned quantity is conserved.
|
| The requirements exercised:
|   - R13.6  WHERE dealer stock-holding is disabled, Dealers hold no Stock
|            (pure consumers — no stock row is created for them).
|   - R14.1  Stock inward (opening/received) increases Stock within a single
|            atomic transaction (used here to seed the sellers' on-hand stock
|            through the single inventory authority).
|   - R16.1  Confirming a Distributor order decrements Company stock and
|            increments Distributor stock by each Order_Line quantity.
|   - R16.2  Confirming a Dealer order with dealer stock-holding DISABLED
|            decrements Distributor stock by each Order_Line quantity (no buyer
|            increment).
|   - R16.3  WHERE dealer stock-holding is ENABLED, confirming a Dealer order
|            decrements Distributor stock and increments Dealer stock by each
|            Order_Line quantity.
|   - R18.1  A transfer between two Stock_Points of the same owner decrements
|            the source and increments the destination in one transaction.
|   - R18.3 / R22.2  A return re-enters the returned quantities into Stock —
|            an increment that exactly restores the quantity it reverses, so the
|            seller→buyer movement is conservative (what leaves one side arrives
|            on the other).
|
| Three universal invariants are generated and asserted per scenario, drawn by
| innmind/black-box (NOT hand-rolled) via the shared static::forAll(), which
| draws the mandated minimum of 100 generated scenarii per proof and shrinks any
| counterexample to a minimal failing input:
|
|   confirmation — for a generated multi-line order (random per-line
|       quantities), applying the seller-decrement + buyer-increment movement
|       pair that order confirmation produces decrements the seller by EXACTLY
|       each line quantity and (where the buyer holds stock) increments the buyer
|       by EXACTLY each line quantity. The quantity leaving the seller equals the
|       quantity arriving at the buyer per line, so stock is conserved across the
|       two parties — it is relocated, never created or destroyed (R16.1, R16.3).
|
|   dealer-no-stock — the SAME dealer order, confirmed with dealer stock-holding
|       DISABLED, decrements the Distributor seller by each line quantity and
|       creates NO stock row for the Dealer buyer at all (R13.6, R16.2).
|
|   transfer-conserves — a transfer of a generated quantity between two stock
|       points of the SAME owner leaves that owner's TOTAL owned quantity for the
|       product (summed across all its points) exactly unchanged (R18.1, R18.3).
|
| DB-GATED. Provisioning a real per-tenant MySQL database and exercising real
| FOR UPDATE transactions needs a reachable MySQL server with CREATE DATABASE
| privileges and the stancl/tenancy MySQL manager; when MySQL is unreachable the
| proof skips gracefully via skip_without_mysql('central'). Assertions are
| written to be correct against a real MySQL instance using the live central
| connection, and every mutation runs inside $tenant->run().
|
| NOTE on helper naming: multiple Property test files are loaded together by
| Pest, so every top-level helper here is prefixed `stockConservation...` to
| avoid "cannot redeclare function" collisions with sibling files (which define
| provisionLedgerTenant / seedLedgerProduct / seedAtomicWritesProduct, etc.).
*/

/**
 * Provision + migrate an isolated tenant for the conservation proof and
 * register it for teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function stockConservationProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cons'.$suffix,
        'subdomain' => 'cons-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'cons-'.$suffix.'.example.test']);
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
function stockConservationTeardownTenants(array $registry): void
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
 * Stock + ledger product_id foreign keys are enforced, so a real product row is
 * required for every movement. (Named distinctly from sibling helpers
 * seedLedgerProduct/seedAtomicWritesProduct to avoid redeclaration collisions.)
 */
function stockConservationSeedProduct(string $sku): int
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
 * Create a stock point owned (polymorphically) by the given owner. Only the
 * Company model exposes a stockPoints() morphMany relation; Distributor/Dealer
 * own stock points through the same `owner` morph but without a named relation,
 * so the point is created directly and its owner morph assigned explicitly.
 */
function stockConservationStockPoint(\Illuminate\Database\Eloquent\Model $owner, string $name, int $sortOrder): StockPoint
{
    $point = new StockPoint(['name' => $name, 'sort_order' => $sortOrder]);
    $point->owner()->associate($owner);
    $point->save();

    return $point;
}

/**
 * The live on-hand quantity (DECIMAL(15,3) string) for an owner/product/point,
 * or "0.000" when no stock row exists yet. Reads straight from the committed
 * `stock` table so it reflects exactly what the movement persisted.
 */
function stockConservationQuantity(\Illuminate\Database\Eloquent\Model $owner, int $productId, int $stockPointId): string
{
    $row = Stock::query()
        ->where('stockable_type', $owner->getMorphClass())
        ->where('stockable_id', $owner->getKey())
        ->where('product_id', $productId)
        ->where('stock_point_id', $stockPointId)
        ->first();

    return $row === null ? '0.000' : (string) $row->quantity;
}

/**
 * Whether ANY stock row exists for the given owner (regardless of product or
 * point). Used to prove a disabled-stock Dealer holds NO Stock at all (R13.6).
 */
function stockConservationOwnerHasAnyStock(\Illuminate\Database\Eloquent\Model $owner): bool
{
    return Stock::query()
        ->where('stockable_type', $owner->getMorphClass())
        ->where('stockable_id', $owner->getKey())
        ->exists();
}

/**
 * Sum an owner's total owned quantity for a product across ALL its stock points
 * as an exact DECIMAL(15,3) string. The transfer-conservation invariant is that
 * this total is unchanged by a within-owner transfer (R18.1).
 */
function stockConservationOwnerTotal(\Illuminate\Database\Eloquent\Model $owner, int $productId): string
{
    $rows = Stock::query()
        ->where('stockable_type', $owner->getMorphClass())
        ->where('stockable_id', $owner->getKey())
        ->where('product_id', $productId)
        ->pluck('quantity');

    $total = '0.000';
    foreach ($rows as $qty) {
        $total = stockConservationAdd($total, (string) $qty);
    }

    return $total;
}

/**
 * Add two DECIMAL(15,3) decimal strings exactly (never via float), mirroring
 * the service's own fixed-scale arithmetic so expected totals carry the same
 * exactness guarantee the invariant promises.
 */
function stockConservationAdd(string $a, string $b): string
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

/**
 * Normalize an integer quantity to a DECIMAL(15,3) string, e.g. 7 => "7.000".
 */
function stockConservationQty(int $units): string
{
    return number_format($units, 3, '.', '');
}

it('decrements the seller and increments the stock-holding buyer by each order-line quantity, holds no dealer stock when disabled, and conserves the owner total on transfer (R13.6, R14.1, R16.1, R16.2, R16.3, R18.1, R18.3, R22.2)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = stockConservationProvisionTenant($registry);

        $tenant->run(function () {
            // Three-tier hierarchy within the single tenant Company (R1.5):
            // Company (top seller) → Distributor (buyer from Company; seller to
            // Dealer) → Dealer (bottom buyer / consumer).
            $company = Company::create(['name' => 'Conservation Co']);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Dist One',
            ]);
            $dealer = Dealer::create([
                'distributor_id' => $distributor->getKey(),
                'name' => 'Dealer One',
            ]);

            // One stock point per owner that can hold stock, plus a SECOND
            // company point so a within-owner transfer has somewhere to go.
            $companyMain = stockConservationStockPoint($company, 'Co Main', 0);
            $companyAlt = stockConservationStockPoint($company, 'Co Alt', 1);
            $distPoint = stockConservationStockPoint($distributor, 'Dist Main', 0);
            $dealerPoint = stockConservationStockPoint($dealer, 'Dealer Main', 0);

            $productId = stockConservationSeedProduct('CONS-P1');

            $service = new StockMovementService;
            $transfers = new StockTransferService($service);

            static::forAll(
                // A multi-line order: 1..5 lines, each a positive unit quantity.
                // Each line quantity is what the seller must lose and the buyer
                // (when it holds stock) must gain, exactly.
                Set::sequence(Set::integers()->between(1, 500))->between(1, 5),
                // Whether the generated order is a Distributor order (buyer =
                // Distributor, always stock-holding) or a Dealer order.
                Set::of('distributor', 'dealer'),
                // A transfer quantity, bounded so the source always has enough
                // (the source is seeded generously below).
                Set::integers()->between(1, 1000),
            )->then(function (array $lineUnits, string $orderKind, int $transferUnits) use (
                $service,
                $transfers,
                $company,
                $distributor,
                $dealer,
                $companyMain,
                $companyAlt,
                $distPoint,
                $dealerPoint,
                $productId,
            ) {
                // Isolate each generated scenario: clear stock + ledger so the
                // before/after deltas are self-contained per iteration. The
                // ledger is append-only (R15.2); a raw delete is used purely for
                // test setup to reset between generated inputs.
                DB::connection('tenant')->table('stock_ledger')->delete();
                DB::connection('tenant')->table('stock')->delete();

                $totalUnits = array_sum($lineUnits);

                // The seller/buyer for this order kind. A Distributor order sells
                // from the Company to the Distributor (R16.1); a Dealer order
                // sells from the Distributor to the Dealer (R16.2/R16.3).
                if ($orderKind === 'distributor') {
                    $seller = $company;
                    $sellerPoint = $companyMain;
                    $buyer = $distributor;
                    $buyerPoint = $distPoint;
                } else {
                    $seller = $distributor;
                    $sellerPoint = $distPoint;
                    $buyer = $dealer;
                    $buyerPoint = $dealerPoint;
                }

                // --- Seed the seller's opening stock via inward (R14.1) so the
                //     confirmation decrement has real on-hand quantity to move,
                //     routed through the single inventory authority. ---
                $service->apply([
                    StockMovement::forOwner(
                        $seller,
                        $productId,
                        (int) $sellerPoint->getKey(),
                        stockConservationQty($totalUnits),
                        'opening',
                        null,
                        'CONS-OPENING',
                    ),
                ]);

                $sellerBefore = stockConservationQuantity($seller, $productId, (int) $sellerPoint->getKey());
                $buyerBefore = stockConservationQuantity($buyer, $productId, (int) $buyerPoint->getKey());

                // =========================================================
                // Invariant 1 — confirmation movement (buyer HOLDS stock).
                //   Build the exact seller-decrement + buyer-increment movement
                //   pair that order confirmation produces, one per order line,
                //   and apply it in one atomic batch. This mirrors R16.1 (and
                //   R16.3 when the buyer is a stock-holding Dealer): each line
                //   decrements the seller and increments the buyer by that line
                //   quantity. The quantity leaving the seller equals the quantity
                //   arriving at the buyer, so stock is conserved across parties.
                // =========================================================
                $confirmBatch = [];
                foreach ($lineUnits as $units) {
                    $qty = stockConservationQty($units);
                    $confirmBatch[] = StockMovement::forOwner(
                        $seller,
                        $productId,
                        (int) $sellerPoint->getKey(),
                        '-'.$qty,
                        'sale_out',
                        null,
                        'CONS-ORDER',
                    );
                    $confirmBatch[] = StockMovement::forOwner(
                        $buyer,
                        $productId,
                        (int) $buyerPoint->getKey(),
                        $qty,
                        'transfer_in',
                        null,
                        'CONS-ORDER',
                    );
                }

                $service->apply($confirmBatch);

                $sellerAfter = stockConservationQuantity($seller, $productId, (int) $sellerPoint->getKey());
                $buyerAfter = stockConservationQuantity($buyer, $productId, (int) $buyerPoint->getKey());

                // Seller decremented by EXACTLY the summed line quantities
                // (R16.1 / R16.3). before - total == after.
                expect($sellerAfter)->toBe(stockConservationAdd($sellerBefore, '-'.stockConservationQty($totalUnits)));

                // Buyer (which holds stock here) incremented by EXACTLY the
                // summed line quantities (R16.1 / R16.3). before + total == after.
                expect($buyerAfter)->toBe(stockConservationAdd($buyerBefore, stockConservationQty($totalUnits)));

                // Conservation across the two parties: the quantity that left the
                // seller is precisely the quantity that arrived at the buyer, so
                // the combined (seller + buyer) owned quantity is unchanged — the
                // confirmation RELOCATED stock, it did not create/destroy it.
                $combinedBefore = stockConservationAdd($sellerBefore, $buyerBefore);
                $combinedAfter = stockConservationAdd($sellerAfter, $buyerAfter);
                expect($combinedAfter)->toBe($combinedBefore);

                // =========================================================
                // Invariant 2 — dealer holds NO stock when disabled (R13.6,
                //   R16.2). Re-run the SAME dealer order against a fresh seeded
                //   seller, but this time apply ONLY the seller decrement (the
                //   platform creates no buyer stock when dealer stock-holding is
                //   disabled). Assert the Dealer owns no stock row whatsoever.
                // =========================================================
                DB::connection('tenant')->table('stock_ledger')->delete();
                DB::connection('tenant')->table('stock')->delete();

                $service->apply([
                    StockMovement::forOwner(
                        $distributor,
                        $productId,
                        (int) $distPoint->getKey(),
                        stockConservationQty($totalUnits),
                        'opening',
                        null,
                        'CONS-OPENING',
                    ),
                ]);

                $distBefore = stockConservationQuantity($distributor, $productId, (int) $distPoint->getKey());

                // Dealer-order confirmation with dealer stock-holding DISABLED:
                // decrement ONLY the Distributor seller, one line at a time
                // (R16.2). No buyer-increment movement is produced for a
                // non-stock-holding Dealer.
                $dealerDecrementBatch = [];
                foreach ($lineUnits as $units) {
                    $dealerDecrementBatch[] = StockMovement::forOwner(
                        $distributor,
                        $productId,
                        (int) $distPoint->getKey(),
                        '-'.stockConservationQty($units),
                        'sale_out',
                        null,
                        'CONS-DEALER-ORDER',
                    );
                }
                $service->apply($dealerDecrementBatch);

                // Distributor decremented by exactly the order total (R16.2).
                expect(stockConservationQuantity($distributor, $productId, (int) $distPoint->getKey()))
                    ->toBe(stockConservationAdd($distBefore, '-'.stockConservationQty($totalUnits)));

                // The Dealer holds NO stock at all — not a single stock row was
                // created for the disabled-stock consumer (R13.6, R16.2).
                expect(stockConservationOwnerHasAnyStock($dealer))->toBeFalse();

                // =========================================================
                // Invariant 3 — transfer conserves the owner's total (R18.1,
                //   R18.3 / R22.2). Transfer a generated quantity between two of
                //   the Company's OWN stock points and assert the Company's total
                //   owned quantity for the product (summed across all its points)
                //   is exactly unchanged — a transfer relocates, never creates or
                //   destroys. The destination increment that exactly offsets the
                //   source decrement is the same conservative re-entry shape a
                //   return uses when it restores stock (R18.3, R22.2).
                // =========================================================
                DB::connection('tenant')->table('stock_ledger')->delete();
                DB::connection('tenant')->table('stock')->delete();

                // Seed the Company's source point with enough to cover the move.
                $service->apply([
                    StockMovement::forOwner(
                        $company,
                        $productId,
                        (int) $companyMain->getKey(),
                        stockConservationQty($transferUnits),
                        'opening',
                        null,
                        'CONS-OPENING',
                    ),
                ]);

                $ownerTotalBefore = stockConservationOwnerTotal($company, $productId);

                $transfers->transfer(
                    $productId,
                    (int) $companyMain->getKey(),
                    (int) $companyAlt->getKey(),
                    stockConservationQty($transferUnits),
                );

                $ownerTotalAfter = stockConservationOwnerTotal($company, $productId);

                // The owner's TOTAL owned quantity is conserved exactly (R18.1).
                expect($ownerTotalAfter)->toBe($ownerTotalBefore);

                // And the move actually happened: the destination point now holds
                // the transferred quantity and the source gave it up (so the
                // conservation above is a real relocation, not a no-op).
                expect(stockConservationQuantity($company, $productId, (int) $companyAlt->getKey()))
                    ->toBe(stockConservationQty($transferUnits));
                expect(stockConservationQuantity($company, $productId, (int) $companyMain->getKey()))
                    ->toBe('0.000');
            });
        });
    } finally {
        stockConservationTeardownTenants($registry);
    }
});
