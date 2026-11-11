<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\ProductService;
use App\Services\TenantProvisioningService;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| Task 7.5 — SKU uniqueness scope (R11.1)
|--------------------------------------------------------------------------
|
| Exercises Product SKU uniqueness against real provisioned tenant databases:
|   • a duplicate SKU WITHIN a single tenant database is rejected — the
|     UNIQUE(`sku`) index is the authoritative guarantee (R11.1);
|   • the SAME SKU is accepted across two DIFFERENT provisioned tenant
|     databases, because each tenant owns its own `products` table so SKU
|     uniqueness is per-tenant automatically (physical isolation, R11.1).
|
| Every test is DB-GATED (needs MySQL + CREATE DATABASE for stancl tenancy) and
| skips gracefully when MySQL is unreachable, mirroring the catalog/management
| suites (DistributorDealerManagementTest, ProspectApprovalFlowTest).
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision a plan-backed tenant for SKU tests, returning it.
 */
function provisionSkuTenant(string $suffix, ?Plan &$plan = null): Tenant
{
    $plan = Plan::create([
        'name' => 'Sku '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    $result = app(TenantProvisioningService::class)->provision([
        'subdomain' => 'sku-'.$suffix,
        'plan_id' => $plan->getKey(),
        'currency' => 'USD',
        'company_name' => 'Sku Co '.$suffix,
        'admin_name' => 'Sku Admin '.$suffix,
        'admin_email' => 'sku-admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return $result['tenant'];
}

function teardownSkuTenant(?Tenant $tenant, ?Plan $plan): void
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
 * Seed the catalog hierarchy a Product requires and return a product attribute
 * array for the given SKU, run inside the active tenant database.
 *
 * @return array{sku: string, name: string, collection_id: int, colour_id: int, tax_type: string, uom: string}
 */
function skuProductAttributes(string $sku): array
{
    $category = Category::create(['name' => 'Cat '.$sku]);
    $collection = Collection::create(['category_id' => $category->getKey(), 'name' => 'Coll '.$sku]);
    $colour = Colour::create(['collection_id' => $collection->getKey(), 'name' => 'Col '.$sku]);

    return [
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'collection_id' => $collection->getKey(),
        'colour_id' => $colour->getKey(),
        'tax_type' => 'GST18',
        'uom' => 'EACH',
    ];
}

it('rejects a duplicate SKU within the same tenant database (R11.1)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionSkuTenant($suffix, $plan);

    try {
        $tenant->run(function () {
            $service = app(ProductService::class);

            // First product with the SKU is persisted.
            $first = $service->create(skuProductAttributes('SKU-DUP-001'));
            expect($first)->toBeInstanceOf(Product::class);
            expect(Product::query()->where('sku', 'SKU-DUP-001')->count())->toBe(1);

            // A second product reusing the same SKU in THIS tenant database is
            // rejected by the UNIQUE(`sku`) index — the authoritative guarantee
            // of per-tenant SKU uniqueness (R11.1).
            $thrown = null;
            try {
                $service->create(skuProductAttributes('SKU-DUP-001'));
            } catch (QueryException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(QueryException::class);
            // MySQL unique-constraint violation is SQLSTATE 23000.
            expect($thrown->getCode())->toBe('23000');

            // The rejected insert persisted nothing: still exactly one row.
            expect(Product::query()->where('sku', 'SKU-DUP-001')->count())->toBe(1);
        });
    } finally {
        teardownSkuTenant($tenant, $plan);
    }
});

it('allows the same SKU across two different tenant databases (R11.1)', function () {
    $suffixA = strtolower(bin2hex(random_bytes(4)));
    $suffixB = strtolower(bin2hex(random_bytes(4)));
    $planA = null;
    $planB = null;
    $tenantA = provisionSkuTenant($suffixA, $planA);
    $tenantB = provisionSkuTenant($suffixB, $planB);

    // The shared SKU reused across both tenants.
    $sharedSku = 'SKU-SHARED-777';

    try {
        // Tenant A owns a product with the shared SKU in its own database.
        $tenantA->run(function () use ($sharedSku) {
            $productA = app(ProductService::class)->create(skuProductAttributes($sharedSku));

            expect($productA->sku)->toBe($sharedSku);
            expect(Product::query()->where('sku', $sharedSku)->count())->toBe(1);
        });

        // Tenant B creates a product with the SAME SKU; because each tenant has
        // its own `products` table, uniqueness is per-tenant and this succeeds
        // without any cross-tenant collision (R11.1 — physical isolation).
        $tenantB->run(function () use ($sharedSku) {
            $productB = app(ProductService::class)->create(skuProductAttributes($sharedSku));

            expect($productB->sku)->toBe($sharedSku);
            // Tenant B's database holds exactly its own single row for the SKU.
            expect(Product::query()->where('sku', $sharedSku)->count())->toBe(1);
        });

        // Tenant A is unaffected: it still holds exactly its own single row.
        $tenantA->run(function () use ($sharedSku) {
            expect(Product::query()->where('sku', $sharedSku)->count())->toBe(1);
        });
    } finally {
        teardownSkuTenant($tenantA, $planA);
        teardownSkuTenant($tenantB, $planB);
    }
});
