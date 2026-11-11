<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanLimit;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Product catalog management (R11.1, R11.3, R5.1).
 *
 * Focused single-responsibility service the thin {@see \App\Http\Controllers\ProductController}
 * delegates to. Responsibilities:
 *
 *   • Create a Product only after the `max_products` quota is satisfied
 *     (R5.1): {@see QuotaService::assertAllows()} throws a 403 naming the quota
 *     when the tenant is at its limit, BEFORE any row is written.
 *   • Persist the Product with its Collection, Colour, tax type and unit of
 *     measure (R11.3). SKU uniqueness within the tenant database (R11.1) is
 *     guaranteed by the UNIQUE(`sku`) index; a duplicate surfaces as the
 *     database's unique-violation.
 *   • Keep the cached product count correct by invalidating the
 *     `max_products` count after a create (R5.6).
 *   • List products paginated with the catalog hierarchy eager-loaded so the
 *     list endpoint never N+1s and selects only what it needs (design:
 *     thin controllers / paginated, eager-loaded list endpoints).
 */
class ProductService
{
    public function __construct(private readonly QuotaService $quota) {}

    /**
     * Paginated product list with collection, its category, and colour eager-
     * loaded to avoid N+1 queries. Ordered by SKU for stable, consistent paging.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Product::query()
            ->with(['collection.category', 'colour'])
            ->orderBy('sku')
            ->paginate($perPage);
    }

    /**
     * Create a Product within quota (R5.1, R11.1, R11.3).
     *
     * Enforces `max_products` BEFORE insertion so a tenant at its limit is
     * rejected with a 403 naming the quota and no row is written. On success
     * the product is persisted with its collection, colour, tax type and uom,
     * and the cached product count is invalidated so the next quota check and
     * the usage widget see the new total (R5.6).
     *
     * @param  array{sku: string, name: string, collection_id: int, colour_id: int, tax_type: string, uom: string}  $attributes
     *
     * @throws \App\Exceptions\QuotaExceededException when the product quota is reached (HTTP 403).
     */
    public function create(array $attributes): Product
    {
        $this->quota->assertAllows(PlanLimit::MAX_PRODUCTS);

        $product = Product::create($attributes);

        // A new counted resource exists: drop the stale cached count so the
        // next quota comparison and usage snapshot recompute (R5.6).
        $this->quota->invalidate(PlanLimit::MAX_PRODUCTS);

        return $product;
    }

    /**
     * Update an existing Product (R11.3). Updating does not change the product
     * count, so no quota check or cache invalidation is required. SKU
     * uniqueness within the tenant remains enforced by the UNIQUE index (R11.1).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        $product->fill($attributes);
        $product->save();

        return $product->refresh();
    }

    /**
     * Delete a Product (R11.3). Deleting frees a counted resource, so the
     * cached `max_products` count is invalidated afterwards so the next quota
     * check and the usage widget recompute the lower total (R5.6).
     */
    public function delete(Product $product): void
    {
        $product->delete();

        $this->quota->invalidate(PlanLimit::MAX_PRODUCTS);
    }
}
