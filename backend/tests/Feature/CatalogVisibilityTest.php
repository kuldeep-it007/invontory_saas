<?php

declare(strict_types=1);

use App\Models\ActiveProductAttribute;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CatalogVisibilityService;
use App\Services\DistributorService;

/*
|--------------------------------------------------------------------------
| Task 7.6 — Active product attributes & catalog visibility (R11.4–R11.6)
|--------------------------------------------------------------------------
|
| Exercises CatalogVisibilityService against a real provisioned tenant database:
|   • an unconfigured Buyer sees every tenant Product (R11.6);
|   • a Buyer configured with a Category/Collection/Colour sees only Products
|     matching the configured allow-list across each configured dimension
|     (R11.4, R11.5);
|   • visibility is per-Buyer and applies to both Distributor and Dealer buyers
|     (polymorphic buyer).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the catalog/stock suites.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for catalog visibility tests.
 *
 * @return array{tenant: Tenant, plan: Plan}
 */
function provisionCatalogVisibilityTenant(): array
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    $plan = Plan::create([
        'name' => 'Vis '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    $result = app(\App\Services\TenantProvisioningService::class)->provision([
        'subdomain' => 'vis-'.$suffix,
        'plan_id' => $plan->getKey(),
        'currency' => 'USD',
        'company_name' => 'Vis Co '.$suffix,
        'admin_name' => 'Vis Admin '.$suffix,
        'admin_email' => 'vis-admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return ['tenant' => $result['tenant'], 'plan' => $plan];
}

function teardownCatalogVisibilityTenant(?Tenant $tenant, ?Plan $plan): void
{
    if ($tenant !== null && $tenant->exists) {
        try {
            Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
            $tenant->database()->getTenantDatabaseManager()->deleteDatabase($tenant);
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $tenant->domains()->delete();
            $tenant->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }

    $plan?->delete();
}

/**
 * Seed a small catalog of two full Category→Collection→Colour→Product chains
 * inside the active tenant database and return the created models keyed for
 * readable assertions.
 *
 * @return array<string, mixed>
 */
function seedVisibilityCatalog(): array
{
    $catA = Category::create(['name' => 'Category A']);
    $catB = Category::create(['name' => 'Category B']);

    $colA = Collection::create(['category_id' => $catA->id, 'name' => 'Collection A']);
    $colB = Collection::create(['category_id' => $catB->id, 'name' => 'Collection B']);

    $clrA = Colour::create(['collection_id' => $colA->id, 'name' => 'Red']);
    $clrB = Colour::create(['collection_id' => $colB->id, 'name' => 'Blue']);

    $prodA = Product::create([
        'sku' => 'SKU-A', 'name' => 'Product A',
        'collection_id' => $colA->id, 'colour_id' => $clrA->id,
        'tax_type' => 'gst_18', 'uom' => 'pcs',
    ]);
    $prodB = Product::create([
        'sku' => 'SKU-B', 'name' => 'Product B',
        'collection_id' => $colB->id, 'colour_id' => $clrB->id,
        'tax_type' => 'gst_18', 'uom' => 'pcs',
    ]);

    return compact('catA', 'catB', 'colA', 'colB', 'clrA', 'clrB', 'prodA', 'prodB');
}

it('shows all tenant products to an unconfigured buyer (R11.6)', function () {
    ['tenant' => $tenant, 'plan' => $plan] = provisionCatalogVisibilityTenant();

    try {
        $tenant->run(function () {
            seedVisibilityCatalog();

            $distributor = app(DistributorService::class)->create(['name' => 'Unconfigured Dist']);

            $visible = app(CatalogVisibilityService::class)
                ->paginateForBuyer($distributor)
                ->pluck('sku')
                ->sort()
                ->values()
                ->all();

            // No active attributes configured ⇒ the whole catalog is visible.
            expect($distributor->activeProductAttributes()->count())->toBe(0);
            expect($visible)->toBe(['SKU-A', 'SKU-B']);
        });
    } finally {
        teardownCatalogVisibilityTenant($tenant, $plan);
    }
});

it('restricts a configured buyer to the allowed category (R11.4, R11.5)', function () {
    ['tenant' => $tenant, 'plan' => $plan] = provisionCatalogVisibilityTenant();

    try {
        $tenant->run(function () {
            $catalog = seedVisibilityCatalog();

            $distributor = app(DistributorService::class)->create(['name' => 'Scoped Dist']);

            // Grant only Category A: Product A (in Collection A under Cat A) is
            // visible; Product B (under Cat B) is not.
            ActiveProductAttribute::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'attribute_type' => Category::class,
                'attribute_id' => $catalog['catA']->id,
            ]);

            $visible = app(CatalogVisibilityService::class)
                ->paginateForBuyer($distributor)
                ->pluck('sku')
                ->all();

            expect($visible)->toBe(['SKU-A']);
        });
    } finally {
        teardownCatalogVisibilityTenant($tenant, $plan);
    }
});

it('restricts across configured collection and colour dimensions independently (R11.5)', function () {
    ['tenant' => $tenant, 'plan' => $plan] = provisionCatalogVisibilityTenant();

    try {
        $tenant->run(function () {
            $catalog = seedVisibilityCatalog();

            $distributor = app(DistributorService::class)->create(['name' => 'Dim Dist']);

            // Collection B allowed AND Colour B allowed ⇒ only Product B
            // satisfies both configured dimensions.
            ActiveProductAttribute::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'attribute_type' => Collection::class,
                'attribute_id' => $catalog['colB']->id,
            ]);
            ActiveProductAttribute::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'attribute_type' => Colour::class,
                'attribute_id' => $catalog['clrB']->id,
            ]);

            $visible = app(CatalogVisibilityService::class)
                ->paginateForBuyer($distributor)
                ->pluck('sku')
                ->all();

            expect($visible)->toBe(['SKU-B']);

            // A conflicting configuration (Collection B but Colour A) yields an
            // empty catalog because no product matches both dimensions.
            $conflicting = app(DistributorService::class)->create(['name' => 'Conflict Dist']);
            ActiveProductAttribute::create([
                'buyer_type' => $conflicting->getMorphClass(),
                'buyer_id' => $conflicting->getKey(),
                'attribute_type' => Collection::class,
                'attribute_id' => $catalog['colB']->id,
            ]);
            ActiveProductAttribute::create([
                'buyer_type' => $conflicting->getMorphClass(),
                'buyer_id' => $conflicting->getKey(),
                'attribute_type' => Colour::class,
                'attribute_id' => $catalog['clrA']->id,
            ]);

            expect(app(CatalogVisibilityService::class)->paginateForBuyer($conflicting)->total())->toBe(0);
        });
    } finally {
        teardownCatalogVisibilityTenant($tenant, $plan);
    }
});

it('applies per-buyer visibility to a Dealer buyer (polymorphic) (R11.4)', function () {
    ['tenant' => $tenant, 'plan' => $plan] = provisionCatalogVisibilityTenant();

    try {
        $tenant->run(function () {
            $catalog = seedVisibilityCatalog();

            $distributor = app(DistributorService::class)->create(['name' => 'Parent Dist']);
            $dealer = Dealer::create([
                'distributor_id' => $distributor->getKey(),
                'name' => 'Scoped Dealer',
            ]);

            ActiveProductAttribute::create([
                'buyer_type' => $dealer->getMorphClass(),
                'buyer_id' => $dealer->getKey(),
                'attribute_type' => Category::class,
                'attribute_id' => $catalog['catB']->id,
            ]);

            // Dealer is restricted to Cat B ⇒ only Product B; the Distributor,
            // being unconfigured, still sees the whole catalog (R11.6).
            expect(app(CatalogVisibilityService::class)->paginateForBuyer($dealer)->pluck('sku')->all())
                ->toBe(['SKU-B']);
            expect(app(CatalogVisibilityService::class)->paginateForBuyer($distributor)->total())
                ->toBe(2);
        });
    } finally {
        teardownCatalogVisibilityTenant($tenant, $plan);
    }
});
