<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActiveProductAttribute;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the catalog a specific Buyer may browse (R11.4, R11.5, R11.6).
 *
 * Focused single-responsibility service the thin
 * {@see \App\Http\Controllers\CatalogController} delegates to for the
 * `GET /catalog` endpoint. All work runs on the active tenant connection
 * (resolved by the tenancy middleware), so visibility is computed entirely
 * within the current tenant's database (physical isolation).
 *
 * Visibility rules:
 *   • A Buyer's active-product-attribute rows (R11.4) may reference any mix of
 *     Categories, Collections, and Colours. Each attribute TYPE forms an
 *     independent allow-list DIMENSION.
 *   • A Product is visible iff, for every dimension the Buyer has configured, the
 *     Product matches one of the allowed values in that dimension (R11.4, R11.5):
 *       - Category dimension: the Product's Collection's `category_id` is allowed.
 *       - Collection dimension: the Product's `collection_id` is allowed.
 *       - Colour dimension: the Product's `colour_id` is allowed.
 *     A dimension the Buyer has NOT configured imposes no restriction.
 *   • WHERE the Buyer has NO active-attribute rows at all, no restriction
 *     applies and every tenant Product is visible (R11.6).
 *
 * The query is paginated and eager-loads the catalog hierarchy so the endpoint
 * never N+1s, per the list-endpoint convention.
 */
class CatalogVisibilityService
{
    /**
     * Paginated catalog visible to the given Buyer (R11.4, R11.5, R11.6).
     *
     * @param  Model  $buyer  A Distributor or Dealer whose visibility is applied.
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateForBuyer(Model $buyer, int $perPage = 25): LengthAwarePaginator
    {
        return $this->withCatalog($this->visibleQuery($buyer))->paginate($perPage);
    }

    /**
     * Paginated full tenant catalog, used when no Buyer selector is supplied —
     * equivalent to the unconfigured-Buyer rule of showing every Product
     * (R11.6).
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateForAll(int $perPage = 25): LengthAwarePaginator
    {
        return $this->withCatalog(Product::query())->paginate($perPage);
    }

    /**
     * Apply the shared eager-load + stable ordering used by every catalog
     * listing so the endpoint never N+1s and pages consistently.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function withCatalog(Builder $query): Builder
    {
        return $query->with(['collection.category', 'colour'])->orderBy('sku');
    }

    /**
     * Build the product query restricted to the Buyer's visible catalog.
     *
     * Returns an unrestricted product query when the Buyer has no configured
     * active attributes (R11.6); otherwise applies one allow-list constraint per
     * configured attribute dimension (R11.4, R11.5).
     *
     * @return Builder<Product>
     */
    public function visibleQuery(Model $buyer): Builder
    {
        $allowed = $this->allowedAttributeIds($buyer);

        $query = Product::query();

        // Unconfigured Buyer: no restriction, the whole catalog is visible (R11.6).
        if ($allowed['total'] === 0) {
            return $query;
        }

        // Category dimension (R11.5): restrict to products whose collection
        // belongs to one of the allowed categories.
        if ($allowed[Category::class] !== []) {
            $categoryIds = $allowed[Category::class];
            $query->whereHas('collection', function (Builder $collection) use ($categoryIds): void {
                $collection->whereIn('category_id', $categoryIds);
            });
        }

        // Collection dimension (R11.5): restrict to products in an allowed
        // collection.
        if ($allowed[Collection::class] !== []) {
            $query->whereIn('collection_id', $allowed[Collection::class]);
        }

        // Colour dimension (R11.5): restrict to products carrying an allowed
        // colour.
        if ($allowed[Colour::class] !== []) {
            $query->whereIn('colour_id', $allowed[Colour::class]);
        }

        return $query;
    }

    /**
     * Collect the Buyer's configured allow-list ids grouped by attribute type.
     *
     * A single indexed query over the leading (buyer_type, buyer_id) prefix
     * (R38.4) returns every grant; the `total` key reports whether the Buyer is
     * configured at all so the caller can short-circuit to "show everything"
     * (R11.6).
     *
     * @return array{
     *     \App\Models\Category::class: list<int>,
     *     \App\Models\Collection::class: list<int>,
     *     \App\Models\Colour::class: list<int>,
     *     total: int
     * }
     */
    private function allowedAttributeIds(Model $buyer): array
    {
        $grants = ActiveProductAttribute::query()
            ->where('buyer_type', $buyer->getMorphClass())
            ->where('buyer_id', $buyer->getKey())
            ->get(['attribute_type', 'attribute_id']);

        $allowed = [
            Category::class => [],
            Collection::class => [],
            Colour::class => [],
            'total' => $grants->count(),
        ];

        foreach ($grants as $grant) {
            if (array_key_exists($grant->attribute_type, $allowed)) {
                $allowed[$grant->attribute_type][] = (int) $grant->attribute_id;
            }
        }

        return $allowed;
    }
}
