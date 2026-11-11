<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\StorePriceRequest;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;

/**
 * Pricing & discount configuration endpoints (R12.1, R12.2, R12.3). Thin
 * controller: validates input via form requests and delegates all logic to
 * {@see PriceService}. Runs on the tenant pipeline, so every action is confined
 * to the active tenant database. The order-line price RESOLUTION itself is
 * performed by {@see \App\Services\PriceResolutionService} during ordering.
 */
class PriceController extends Controller
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * GET /api/prices — paginated list of configured prices (R12.1).
     */
    public function index(): JsonResponse
    {
        return response()->json($this->prices->paginatePrices());
    }

    /**
     * POST /api/prices — configure a product- or collection-level price with an
     * optional collection markup/MUP (R12.1, R12.2). Returns 201 on success.
     */
    public function store(StorePriceRequest $request): JsonResponse
    {
        $price = $this->prices->createPrice($request->validated());

        return response()->json(['data' => $price], 201);
    }

    /**
     * GET /api/discounts — paginated list of configured discounts (R12.3).
     */
    public function discounts(): JsonResponse
    {
        return response()->json($this->prices->paginateDiscounts());
    }

    /**
     * POST /api/discounts — configure a `customer`/`online` discount (R12.3);
     * any other type is rejected with a 422. Returns 201 on success.
     */
    public function storeDiscount(StoreDiscountRequest $request): JsonResponse
    {
        $discount = $this->prices->createDiscount($request->validated());

        return response()->json(['data' => $discount], 201);
    }

    /**
     * PATCH /api/discounts/{discount} — update percentage/scope/active (R12.5).
     */
    public function updateDiscount(\Illuminate\Http\Request $request, \App\Models\Discount $discount): JsonResponse
    {
        $data = $request->validate([
            'customer_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.categories,id'],
            'collection_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.collections,id'],
            'colour_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.colours,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->prices->updateDiscount($discount, $data)]);
    }

    /** DELETE /api/discounts/{discount} — remove a discount. */
    public function destroyDiscount(\App\Models\Discount $discount): JsonResponse
    {
        $this->prices->deleteDiscount($discount);

        return response()->json(['message' => 'Discount deleted.']);
    }

    /** DELETE /api/prices/{price} — remove a price configuration. */
    public function destroyPrice(\App\Models\Price $price): JsonResponse
    {
        $this->prices->deletePrice($price);

        return response()->json(['message' => 'Price deleted.']);
    }
}
