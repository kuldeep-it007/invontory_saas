<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;

/**
 * Product catalog endpoints (R11.1, R11.3). Thin controller: validates input
 * via form requests and delegates all logic to {@see ProductService}. Runs on
 * the tenant pipeline, so every action is confined to the active tenant
 * database.
 */
class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/products — paginated product list with the catalog hierarchy
     * eager-loaded (R11.3). Paginated and eager-loaded per the list-endpoint
     * convention.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->products->paginate());
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
        $product = $this->products->create($request->validated());

        return response()->json(['data' => $product], 201);
    }

    /**
     * PATCH /api/products/{product} — update a product (R11.3).
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->update($product, $request->validated());

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
