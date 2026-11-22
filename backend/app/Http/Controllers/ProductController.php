<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\PriceResolutionService;
use App\Services\ProductService;
use App\Services\ResolvedPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product catalog endpoints (R11.1, R11.3). Thin controller: validates input
 * via form requests and delegates all logic to {@see ProductService}. Runs on
 * the tenant pipeline, so every action is confined to the active tenant
 * database.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly PriceResolutionService $prices,
    ) {}

    /**
     * GET /api/products — paginated product list with the catalog hierarchy
     * eager-loaded (R11.3). Paginated and eager-loaded per the list-endpoint
     * convention.
     */
    public function index(Request $request): JsonResponse
    {
        $page = $this->products->paginate(max(1, min($request->integer('per_page', 25), 100)));
        $resolved = $this->prices->resolveMany($page->getCollection());
        $page->getCollection()->each(fn (Product $product) => $this->attachPricing($product, $resolved[(int) $product->getKey()] ?? null));

        return response()->json($page);
    }

    /**
     * The product's final price for a general buyer: base price, markup, then
     * the single highest-priority discount (no dealer/distributor-specific
     * discount, since no buyer is selected). Null when no base price exists.
     */
    private function attachPricing(Product $product, ?ResolvedPrice $resolved): Product
    {
        return $product->setAttribute('pricing', $resolved === null ? null : [
            'base_price' => number_format($resolved->markedUp, 2, '.', ''),
            'final_price' => number_format($resolved->final, 2, '.', ''),
            'currency' => $resolved->currency,
            'discount' => $resolved->toArray()['discount'],
        ]);
    }

    /** Resolve and attach pricing for a single product. */
    private function withPricing(Product $product): Product
    {
        return $this->attachPricing($product, $this->prices->resolveMany(collect([$product]))[(int) $product->getKey()] ?? null);
    }

    /**
     * POST /api/products — create a product within quota (R5.1, R11.1, R11.3).
     *
     * The `max_products` quota is enforced in the service before insertion; a
     * tenant at its limit receives a 403 naming the quota. Returns 201 with the
     * created product on success.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->withPricing($this->products->create($request->validated()));

        return response()->json(['data' => $product], 201);
    }

    /**
     * PATCH /api/products/{product} — update a product (R11.3).
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->withPricing($this->products->update($product, $request->validated()));

        return response()->json(['data' => $product]);
    }

    /**
     * DELETE /api/products/{product} — delete a product (R11.3). Frees a
     * `max_products` quota slot; the service invalidates the cached count.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(['message' => 'Product deleted.']);
    }
}
