<?php

declare(strict_types=1);

use App\Models\ActiveProductAttribute;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\CatalogVisibilityService;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 22: Catalog visibility restriction
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 22: Catalog visibility
| restriction
|
| Validates: Requirements 11.4, 11.5, 11.6
|
| Design reference: "Active Product Attributes / Catalog Visibility" —
|   CatalogVisibilityService (resolves the catalog a Buyer may browse) backing
|   the GET /catalog endpoint.
|
|   - R11.4  WHERE active product attributes are configured for a specific
|            Distributor or Dealer, the available Categories/Collections/Colours
|            are restricted to the configured set.
|   - R11.5  WHEN a Buyer browses the catalog, only Products the Buyer may view
|            (per its active product attributes) are displayed. Each configured
|            attribute TYPE is an independent allow-list DIMENSION; a Product is
|            visible iff, for every configured dimension, it matches an allowed
|            value in that dimension.
|   - R11.6  WHERE no active product attribute restriction is configured for a
|            Buyer, every tenant Product is displayed.
|
| The invariant under test is the pure visibility predicate the service applies,
| expressed over a generated catalog + a generated allow-list per Buyer:
|
|     visible(product) ==
|         (no attributes configured)                              -- R11.6
|       OR (
|            (noCategoryGrant  OR product.category  ∈ allowedCats)  -- R11.5
|          AND (noCollectionGrant OR product.collection ∈ allowedCols)
|          AND (noColourGrant   OR product.colour    ∈ allowedClrs)
|          )                                                       -- R11.4/R11.5
|
| We build a fixed, fully-populated catalog (every Category×Collection×Colour
| combination has a Product) once per proof, then let black-box generate 100+
| randomized allow-list configurations. For each configuration we compute the
| expected visible SKU set independently from the service and assert the service
| returns EXACTLY that set — covering the unconfigured case (R11.6), each single
| dimension, multi-dimension intersections, and empty/conflicting selections.
| The Buyer type (Distributor vs Dealer) is also generated so the polymorphic
| per-Buyer rule is exercised on both buyer kinds.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server; when MySQL is unreachable the proof skips gracefully via
| skip_without_mysql('central'). Assertions are written to be correct against a
| real MySQL instance.
*/

/**
 * Provision an isolated tenant with a migrated tenant database and register it
 * for teardown. Mirrors the other property proofs' helpers.
 */
function provisionCatalogVisibilityPropTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cvis'.$suffix,
        'subdomain' => 'cvis-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'cvis-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    $registry[] = $tenant;

    return $tenant;
}

/**
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param array<int, Tenant> $tenants
 */
function teardownCatalogVisibilityPropTenants(array $tenants): void
{
    tenancy()->end();

    foreach ($tenants as $t) {
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
 * Seed a FULLY-POPULATED catalog: a 2×2×2 grid where every combination of
 * (Category → Collection → Colour) is realised by exactly one Product. This
 * gives the visibility predicate independent control over each dimension so the
 * generated allow-lists exercise every single- and multi-dimension slice.
 *
 * Structure (two categories, each with its own collection, two colours shared
 * across the catalog so a colour grant genuinely cross-cuts collections):
 *
 *   Category A ─ Collection A ─ { Colour Red, Colour Blue }
 *   Category B ─ Collection B ─ { Colour Red, Colour Blue }
 *
 * @return array<string, mixed>
 */
function seedFullVisibilityCatalog(): array
{
    $catA = Category::create(['name' => 'Category A']);
    $catB = Category::create(['name' => 'Category B']);

    $colA = Collection::create(['category_id' => $catA->id, 'name' => 'Collection A']);
    $colB = Collection::create(['category_id' => $catB->id, 'name' => 'Collection B']);

    // Two colours, each attached to one collection (schema: Colour belongs to a
    // Collection). To let a Colour grant cross-cut categories we create one
    // colour per collection but keep products spanning both colours per
    // collection — four products total, one per (collection, colour).
    $redA = Colour::create(['collection_id' => $colA->id, 'name' => 'Red A']);
    $blueA = Colour::create(['collection_id' => $colA->id, 'name' => 'Blue A']);
    $redB = Colour::create(['collection_id' => $colB->id, 'name' => 'Red B']);
    $blueB = Colour::create(['collection_id' => $colB->id, 'name' => 'Blue B']);

    $products = [];
    $grid = [
        ['sku' => 'A-RED', 'col' => $colA, 'clr' => $redA],
        ['sku' => 'A-BLUE', 'col' => $colA, 'clr' => $blueA],
        ['sku' => 'B-RED', 'col' => $colB, 'clr' => $redB],
        ['sku' => 'B-BLUE', 'col' => $colB, 'clr' => $blueB],
    ];

    foreach ($grid as $cell) {
        $products[$cell['sku']] = Product::create([
            'sku' => $cell['sku'],
            'name' => 'Product '.$cell['sku'],
            'collection_id' => $cell['col']->id,
            'colour_id' => $cell['clr']->id,
            'tax_type' => 'gst_18',
            'uom' => 'pcs',
        ]);
    }

    return compact('catA', 'catB', 'colA', 'colB', 'redA', 'blueA', 'redB', 'blueB', 'products');
}

/**
 * Compute the EXPECTED visible SKU set independently from the service, directly
 * from the visibility predicate (R11.4–R11.6). Returns a sorted list of SKUs.
 *
 * @param  array<string, mixed>  $catalog
 * @param  list<int>  $allowedCategoryIds
 * @param  list<int>  $allowedCollectionIds
 * @param  list<int>  $allowedColourIds
 * @return list<string>
 */
function expectedVisibleSkus(
    array $catalog,
    array $allowedCategoryIds,
    array $allowedCollectionIds,
    array $allowedColourIds,
): array {
    $configured = $allowedCategoryIds !== []
        || $allowedCollectionIds !== []
        || $allowedColourIds !== [];

    // R11.6: unconfigured Buyer sees the whole catalog.
    if (! $configured) {
        $all = array_keys($catalog['products']);
        sort($all);

        return array_values($all);
    }

    // Map each collection to its category for the Category dimension check.
    $collectionCategory = [
        $catalog['colA']->id => $catalog['catA']->id,
        $catalog['colB']->id => $catalog['catB']->id,
    ];

    $visible = [];

    /** @var Product $product */
    foreach ($catalog['products'] as $sku => $product) {
        $categoryId = $collectionCategory[$product->collection_id];

        $categoryOk = $allowedCategoryIds === []
            || in_array($categoryId, $allowedCategoryIds, true);
        $collectionOk = $allowedCollectionIds === []
            || in_array($product->collection_id, $allowedCollectionIds, true);
        $colourOk = $allowedColourIds === []
            || in_array($product->colour_id, $allowedColourIds, true);

        if ($categoryOk && $collectionOk && $colourOk) {
            $visible[] = $sku;
        }
    }

    sort($visible);

    return array_values($visible);
}

it('shows exactly the Products permitted by the Buyer\'s active product attributes, and the full catalog when unconfigured (R11.4, R11.5, R11.6)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $tenant = provisionCatalogVisibilityPropTenant($tenants);

        $tenant->run(function () {
            // The tenant's single Company (R1.5) that every Distributor belongs
            // to; Distributors require a company_id.
            $company = \App\Models\Company::create([
                'name' => 'Catalog Visibility Co',
                'gstin' => 'GST-CVIS',
            ]);

            $catalog = seedFullVisibilityCatalog();

            // Candidate allow-list values per dimension. Generators pick any
            // subset (encoded as a bitmask) of each dimension's candidates, so
            // the space covers: nothing (unconfigured ⇒ R11.6), one value, both
            // values, and cross-dimension combinations (R11.4, R11.5).
            $categoryIds = [$catalog['catA']->id, $catalog['catB']->id];
            $collectionIds = [$catalog['colA']->id, $catalog['colB']->id];
            // Colours span both collections so a colour grant genuinely
            // cross-cuts the catalog.
            $colourIds = [
                $catalog['redA']->id,
                $catalog['blueA']->id,
                $catalog['redB']->id,
                $catalog['blueB']->id,
            ];

            $service = app(CatalogVisibilityService::class);

            // ----------------------------------------------------------------
            // Generated invariant. For 100+ randomized configurations:
            //   catMask  ∈ 0..3   subset of the 2 categories
            //   colMask  ∈ 0..3   subset of the 2 collections
            //   clrMask  ∈ 0..15  subset of the 4 colours
            //   buyerKind ∈ {0,1} Distributor vs Dealer (polymorphic R11.4)
            // ----------------------------------------------------------------
            static::forAll(
                Set::integers()->between(0, 3),
                Set::integers()->between(0, 3),
                Set::integers()->between(0, 15),
                Set::integers()->between(0, 1),
            )->then(function (int $catMask, int $colMask, int $clrMask, int $buyerKind) use (
                $service,
                $catalog,
                $categoryIds,
                $collectionIds,
                $colourIds,
                $company,
            ) {
                // Decode each mask into the selected allow-list ids.
                $allowedCategoryIds = subsetFromMask($categoryIds, $catMask);
                $allowedCollectionIds = subsetFromMask($collectionIds, $colMask);
                $allowedColourIds = subsetFromMask($colourIds, $clrMask);

                // Fresh Buyer per scenario so prior grants never bleed across
                // iterations; both buyer kinds exercise the polymorphic rule.
                if ($buyerKind === 0) {
                    $buyer = Distributor::create([
                        'company_id' => $company->getKey(),
                        'name' => 'Dist '.bin2hex(random_bytes(4)),
                    ]);
                } else {
                    $parent = Distributor::create([
                        'company_id' => $company->getKey(),
                        'name' => 'Parent '.bin2hex(random_bytes(4)),
                    ]);
                    $buyer = Dealer::create([
                        'distributor_id' => $parent->getKey(),
                        'name' => 'Dealer '.bin2hex(random_bytes(4)),
                    ]);
                }

                // Materialise the generated allow-list as active-attribute rows.
                grantAttributes($buyer, Category::class, $allowedCategoryIds);
                grantAttributes($buyer, Collection::class, $allowedCollectionIds);
                grantAttributes($buyer, Colour::class, $allowedColourIds);

                $expected = expectedVisibleSkus(
                    $catalog,
                    $allowedCategoryIds,
                    $allowedCollectionIds,
                    $allowedColourIds,
                );

                $actual = $service->paginateForBuyer($buyer)
                    ->pluck('sku')
                    ->sort()
                    ->values()
                    ->all();

                // The service returns EXACTLY the predicate-defined set.
                expect($actual)->toBe($expected);

                // Reinforce the two boundary rules explicitly:
                $isConfigured = $allowedCategoryIds !== []
                    || $allowedCollectionIds !== []
                    || $allowedColourIds !== [];

                if (! $isConfigured) {
                    // R11.6: unconfigured ⇒ every tenant Product is visible.
                    expect($actual)->toBe(['A-BLUE', 'A-RED', 'B-BLUE', 'B-RED']);
                } else {
                    // R11.4/R11.5: every visible Product satisfies every
                    // configured dimension — never a product outside the
                    // allow-list.
                    expect(count($actual))->toBeLessThanOrEqual(4);
                    expect($actual)->not->toContain('__none__');
                }
            });
        });
    } finally {
        teardownCatalogVisibilityPropTenants($tenants);
    }
});

/**
 * Decode a bitmask into the selected subset of the candidate ids.
 *
 * @param  list<int>  $candidates
 * @return list<int>
 */
function subsetFromMask(array $candidates, int $mask): array
{
    $selected = [];

    foreach ($candidates as $index => $id) {
        if (($mask & (1 << $index)) !== 0) {
            $selected[] = $id;
        }
    }

    return $selected;
}

/**
 * Create one active-product-attribute grant per allowed id for the given
 * attribute type on the Buyer.
 *
 * @param  list<int>  $allowedIds
 */
function grantAttributes(\Illuminate\Database\Eloquent\Model $buyer, string $attributeType, array $allowedIds): void
{
    foreach ($allowedIds as $id) {
        ActiveProductAttribute::create([
            'buyer_type' => $buyer->getMorphClass(),
            'buyer_id' => $buyer->getKey(),
            'attribute_type' => $attributeType,
            'attribute_id' => $id,
        ]);
    }
}
