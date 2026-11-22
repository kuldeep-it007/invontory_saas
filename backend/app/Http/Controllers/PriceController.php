<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\StorePriceRequest;
use App\Models\Discount;
use App\Models\Price;
use App\Models\User;
use App\Services\PriceResolutionService;
use App\Services\PriceService;
use App\Support\CatalogOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pricing & discount configuration endpoints (R12.1, R12.2, R12.3). Thin
 * controller: validates input via form requests and delegates all logic to
 * {@see PriceService}. Runs on the tenant pipeline, so every action is confined
 * to the active tenant database. The order-line price RESOLUTION itself is
 * performed by {@see PriceResolutionService} during ordering.
 *
 * Ownership: the Company and each Distributor list and manage only their own
 * prices (prices of their own products/collections) and their own discounts.
 * Another owner's rows answer 404.
 */
class PriceController extends Controller
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * GET /api/prices — paginated list of the caller's configured prices (R12.1).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->prices->paginatePrices($this->ownerKey($request->user())));
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
     * GET /api/discounts — paginated list of the caller's discounts (R12.3).
     */
    public function discounts(Request $request): JsonResponse
    {
        return response()->json($this->prices->paginateDiscounts($this->ownerKey($request->user())));
    }

    /**
     * POST /api/discounts — configure a `customer`/`online` discount (R12.3);
     * any other type is rejected with a 422. The owner is the caller. Returns
     * 201 on success.
     */
    public function storeDiscount(StoreDiscountRequest $request): JsonResponse
    {
        $discount = $this->prices->createDiscount($request->validated(), CatalogOwner::distributorColumnFor($request->user()));

        return response()->json(['data' => $discount], 201);
    }

    /**
     * PATCH /api/discounts/{discount} — update value/scope/active (R12.5).
     */
    public function updateDiscount(Request $request, Discount $discount): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $discount);

        $data = $request->validate(array_merge(
            ['percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100']],
            StoreDiscountRequest::sharedRules($request->user(), $request->input('buyer_type')),
        ));

        return response()->json(['data' => $this->prices->updateDiscount($discount, $data)]);
    }

    /** DELETE /api/discounts/{discount} — remove a discount. */
    public function destroyDiscount(Request $request, Discount $discount): JsonResponse
    {
        CatalogOwner::assertManages($request->user(), $discount);
        $this->prices->deleteDiscount($discount);

        return response()->json(['message' => 'Discount deleted.']);
    }

    /** DELETE /api/prices/{price} — remove a price configuration. */
    public function destroyPrice(Request $request, Price $price): JsonResponse
    {
        if ($this->prices->priceOwnerKey($price) !== CatalogOwner::keyFor($request->user())) {
            throw new NotFoundHttpException('Not found.');
        }
        $this->prices->deletePrice($price);

        return response()->json(['message' => 'Price deleted.']);
    }

    /**
     * The owner key whose rows the caller sees: its own for the Company and
     * distributors; the Company's for other view-only actors.
     */
    private function ownerKey(?User $user): int
    {
        return $user !== null && (CatalogOwner::isCompanyUser($user) || CatalogOwner::distributorIdOf($user) !== null)
            ? CatalogOwner::keyFor($user)
            : CatalogOwner::COMPANY;
    }
}
