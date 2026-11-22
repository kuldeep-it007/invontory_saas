<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\StorePriceRequest;
use App\Models\Discount;
use App\Models\Price;
use App\Services\PriceResolutionService;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pricing & discount configuration endpoints (R12.1, R12.2, R12.3). Thin
 * controller: validates input via form requests and delegates all logic to
 * {@see PriceService}. Runs on the tenant pipeline, so every action is confined
 * to the active tenant database. The order-line price RESOLUTION itself is
 * performed by {@see PriceResolutionService} during ordering.
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
    public function updateDiscount(Request $request, Discount $discount): JsonResponse
    {
        $data = $request->validate([
            'customer_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'value_type' => ['sometimes', 'string', Rule::in(Discount::VALUE_TYPES)],
            'percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required_if:value_type,'.Discount::VALUE_FIXED, 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'buyer_type' => ['sometimes', 'nullable', 'string', Rule::in(['dealer', 'distributor'])],
            'buyer_id' => ['nullable', 'integer', 'required_with:buyer_type',
                Rule::when($request->input('buyer_type') === 'dealer', ['exists:tenant.dealers,id']),
                Rule::when($request->input('buyer_type') === 'distributor', ['exists:tenant.distributors,id'])],
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.products,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.categories,id'],
            'collection_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.collections,id'],
            'colour_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.colours,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->prices->updateDiscount($discount, $data)]);
    }

    /** DELETE /api/discounts/{discount} — remove a discount. */
    public function destroyDiscount(Discount $discount): JsonResponse
    {
        $this->prices->deleteDiscount($discount);

        return response()->json(['message' => 'Discount deleted.']);
    }

    /** DELETE /api/prices/{price} — remove a price configuration. */
    public function destroyPrice(Price $price): JsonResponse
    {
        $this->prices->deletePrice($price);

        return response()->json(['message' => 'Price deleted.']);
    }
}
