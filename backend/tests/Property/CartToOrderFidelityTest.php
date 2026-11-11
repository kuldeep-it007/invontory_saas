<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Price;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\CartSubmissionService;
use App\Services\DistributorService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 9.2 — Property 12: Cart-to-order fidelity
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 12: Cart-to-order fidelity
|
| Validates: Requirements 20.3, 20.4
|
| Property statement (design.md §Property 12):
|   Submitting a cart of N entries creates EXACTLY ONE order with N lines
|   matching products and quantities.
|
| The requirements exercised:
|   - R20.3  A cart accumulates multiple lines before submission; submitting a
|            cart of N entries produces one order holding one line per entry.
|   - R20.4  Cart-to-order fidelity: the submitted order carries exactly one
|            Order_Line per cart entry, and each line records the SAME product
|            and quantity that entry carried — nothing added, dropped, merged,
|            or altered.
|
| What is generated (innmind/black-box via the shared static::forAll(), NOT
| hand-rolled; the framework draws the mandated minimum of 100 scenarii per
| proof and shrinks any counterexample to a minimal failing input):
|
|   cart   — a sequence of 1..MAX_CART_ENTRIES cart entries. Each entry is a
|            (productIndex, quantity) pair:
|              • productIndex ∈ 0..(PRODUCT_POOL-1) selects one of a fixed pool
|                of seeded products. Indices repeat freely across entries, so
|                the generator covers carts that reference the SAME product in
|                multiple entries — proving those stay as DISTINCT lines (one
|                line per entry, never merged — R20.4).
|              • quantity ∈ 1..5000 is the ordered quantity for that entry.
|   buyer  — Distributor vs Dealer, so the fidelity invariant is exercised on
|            both buyer kinds (the order is placed against Company resp. the
|            serving Distributor — R20.1/R20.2 — but fidelity is independent of
|            the seller).
|
| For each generated cart we submit it through the real CartSubmissionService
| against a provisioned tenant database and assert:
|   1. EXACTLY ONE order was created by the submission (R20.3).
|   2. The order has EXACTLY N order lines, where N == count(cart entries)
|      (R20.4 — one line per entry, cardinality preserved).
|   3. The ordered multiset of (product_id, quantity) across the created lines
|      equals the ordered multiset the cart carried (R20.4 — every entry's
|      product and quantity is faithfully reproduced, with duplicates kept).
|
| Each scenario starts from a clean order/line slate (prior iterations' orders
| are deleted) so "exactly one order" is a precise count assertion, not a delta.
|
| DB-GATED. Submitting a cart drives Eloquent writes inside a real transaction
| on a provisioned per-tenant MySQL database (stancl/tenancy); when MySQL is
| unreachable the proof skips gracefully via skip_without_mysql('central'). All
| mutations run inside $tenant->run() so the active tenant connection is the
| tenant's own database.
|
| NOTE on helper naming: Pest loads every Property/Feature test file together,
| so all top-level helpers here are prefixed `cartFidelity...` to avoid
| "cannot redeclare function" collisions with sibling files (notably the
| Feature CartSubmissionTest.php which defines provisionCartTenant /
| seedCartProduct — those names are deliberately NOT reused here).
*/

/**
 * The fixed pool of seeded products a generated cart draws from. Larger than
 * one so carts can reference several distinct products; small enough that the
 * generator frequently reuses an index across entries (exercising repeated
 * products staying as distinct lines).
 */
const CART_FIDELITY_PRODUCT_POOL = 5;

/**
 * The maximum number of entries a generated cart may contain (N varies 1..MAX).
 */
const CART_FIDELITY_MAX_ENTRIES = 6;

/**
 * Provision + migrate an isolated tenant for the fidelity proof, seed its
 * single Company (seller for Distributor orders — R20.1), and register it for
 * teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function cartFidelityProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cfid'.$suffix,
        'subdomain' => 'cfid-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'cfid-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        // The tenant's single Company (seller for Distributor orders — R20.1).
        Company::create(['name' => 'Cart Fidelity Co']);
    });

    return $tenant;
}

/**
 * Seed the fixed pool of priced products the generated carts draw from.
 * Returns the pool as a 0-indexed list so a generated productIndex maps
 * directly onto a Product.
 *
 * @return list<Product>
 */
function cartFidelitySeedProductPool(): array
{
    $pool = [];

    for ($i = 0; $i < CART_FIDELITY_PRODUCT_POOL; $i++) {
        $sku = 'CFID-'.$i;

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Cat '.$sku, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $collectionId = DB::table('collections')->insertGetId([
            'category_id' => $categoryId, 'name' => 'Col '.$sku, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $colourId = DB::table('colours')->insertGetId([
            'collection_id' => $collectionId, 'name' => 'Clr '.$sku, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $product = Product::create([
            'sku' => $sku,
            'name' => 'Product '.$sku,
            'collection_id' => $collectionId,
            'colour_id' => $colourId,
            'tax_type' => 'gst_18',
            'uom' => 'pcs',
        ]);

        // A base price so the submission's line-pricing step has something to
        // resolve; fidelity is about product + quantity, not price.
        Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => 10.0 + $i]);

        $pool[] = $product;
    }

    return $pool;
}

/**
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param  array<int, Tenant>  $registry
 */
function cartFidelityTeardownTenants(array $registry): void
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
 * Normalize a quantity the way CartSubmissionService stores it on the line —
 * DECIMAL(15,3), fixed 3-scale string — so the expected and actual multisets
 * are compared on identical representations.
 */
function cartFidelityQuantityKey(string|int|float $quantity): string
{
    return number_format((float) $quantity, 3, '.', '');
}

it('creates exactly one order with one faithful line per cart entry for a generated cart of N entries (R20.3, R20.4)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = cartFidelityProvisionTenant($registry);

        $tenant->run(function () {
            $pool = cartFidelitySeedProductPool();
            $service = app(CartSubmissionService::class);

            // A single reusable Distributor and Dealer; the fidelity invariant
            // is independent of which buyer/seller the order is placed against,
            // so both buyer kinds reuse one buyer each across scenarios.
            $distributor = app(DistributorService::class)->create(['name' => 'Fidelity Distributor']);
            $dealer = Dealer::create([
                'distributor_id' => $distributor->getKey(),
                'name' => 'Fidelity Dealer',
            ]);

            // ----------------------------------------------------------------
            // Generated invariant. For 100+ randomized scenarii:
            //   cart      — 1..MAX_ENTRIES entries, each a (productIndex, qty)
            //               pair; indices may repeat so same-product entries
            //               stay DISTINCT lines.
            //   buyerKind — 0 Distributor (seller = Company), 1 Dealer
            //               (seller = serving Distributor).
            // ----------------------------------------------------------------
            static::forAll(
                Set::sequence(
                    Set::compose(
                        static fn (int $productIndex, int $quantity): array => [$productIndex, $quantity],
                        Set::integers()->between(0, CART_FIDELITY_PRODUCT_POOL - 1),
                        Set::integers()->between(1, 5000),
                    ),
                )->between(1, CART_FIDELITY_MAX_ENTRIES),
                Set::integers()->between(0, 1),
            )->then(function (array $cart, int $buyerKind) use ($service, $pool, $distributor, $dealer) {
                // Clean slate so "exactly one order" is an absolute count, not
                // a delta against prior iterations' orders.
                OrderLine::query()->delete();
                Order::query()->delete();

                $buyer = $buyerKind === 0 ? $distributor : $dealer;

                // Translate the generated cart into submission entries AND the
                // expected (product_id, quantity) multiset the order's lines
                // must faithfully reproduce (R20.4).
                $entries = [];
                $expected = [];

                foreach ($cart as [$productIndex, $quantity]) {
                    $product = $pool[$productIndex];

                    $entries[] = [
                        'product_id' => $product->getKey(),
                        'quantity' => (string) $quantity,
                    ];

                    $expected[] = $product->getKey().'@'.cartFidelityQuantityKey($quantity);
                }

                $n = count($entries);

                $order = $service->submit($buyer, $entries);

                // 1. EXACTLY ONE order was created by the submission (R20.3).
                expect(Order::query()->count())->toBe(1);

                // 2. The order has EXACTLY N lines — one per cart entry (R20.4).
                $lines = OrderLine::query()->where('order_id', $order->getKey())->get();
                expect($lines)->toHaveCount($n);
                expect(OrderLine::query()->count())->toBe($n);

                // The persisted order is Placed — a submitted cart is a placed
                // order (R21.1), confirming the single order is the submission's.
                expect($order->fresh()->status)->toBe(OrderStatus::PLACED);

                // 3. The multiset of (product, quantity) across the created
                //    lines equals the cart's — every entry faithfully present,
                //    duplicates kept, nothing added/dropped/merged (R20.4).
                $actual = $lines
                    ->map(fn (OrderLine $line) => $line->product_id.'@'.cartFidelityQuantityKey($line->quantity))
                    ->all();

                sort($expected);
                sort($actual);

                expect($actual)->toBe($expected);
            });
        });
    } finally {
        cartFidelityTeardownTenants($registry);
    }
});
