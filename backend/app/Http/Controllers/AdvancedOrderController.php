<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PlaceAdvancedOrderRequest;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Stock;
use App\Services\AdvancedOrderService;
use App\Services\StockReservationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Advanced order types & stock reservations HTTP surface (R23).
 *
 * Thin controller: it validates input via {@see PlaceAdvancedOrderRequest} and
 * delegates all business rules — the `advance_orders` feature gate (R23.2),
 * depot stock-point resolution (R23.3), scheduled blanket releases (R23.1), and
 * the reserve/available arithmetic (R23.4) — to {@see AdvancedOrderService} and
 * {@see StockReservationService}. It runs on the tenant pipeline, so every
 * action is confined to the active tenant database.
 */
class AdvancedOrderController extends Controller
{
    public function __construct(
        private readonly AdvancedOrderService $advancedOrders,
        private readonly StockReservationService $reservations,
    ) {}

    /**
     * POST /api/orders/advanced — place an advanced order (blanket/advance/
     * depot) and reserve the stock it blocks (R23.1–R23.4).
     *
     * An advance order is denied with a 403 when the `advance_orders` flag is
     * off (R23.2); a reservation that would exceed available is rejected with a
     * 422 (R23.4). Returns the created order with its lines on success.
     */
    public function store(PlaceAdvancedOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $buyer = $this->resolveBuyer($data['buyer_type'], (int) $data['buyer_id']);

        $order = $this->advancedOrders->place(
            $buyer,
            $data['order_type'],
            $data['lines'],
            [
                'total' => isset($data['total']) ? (string) $data['total'] : '0',
                'actor_id' => $request->user()?->getKey(),
            ],
        );

        return response()->json(['data' => $order->load('lines')], 201);
    }

    /**
     * GET /api/stock/{stock}/availability — the available quantity of a stock
     * row: on-hand minus the sum of its active reservations (R23.4).
     *
     * Surfaces the three figures the caller needs to reason about blocked
     * stock: `on_hand`, `reserved` (Σ active reservations), and `available`.
     */
    public function availability(Stock $stock): JsonResponse
    {
        return response()->json([
            'data' => [
                'stock_id' => (int) $stock->getKey(),
                'on_hand' => (string) $stock->quantity,
                'reserved' => $this->reservations->reservedQuantity((int) $stock->getKey()),
                'available' => $this->reservations->availableQuantity($stock),
            ],
        ]);
    }

    /**
     * Resolve the Buyer model from the request's morph type + id (R20.1,
     * R20.2). The type is restricted to `distributor`/`dealer` by the form
     * request; the record must exist in the active tenant database.
     */
    private function resolveBuyer(string $buyerType, int $buyerId): Model
    {
        return match ($buyerType) {
            'distributor' => Distributor::query()->findOrFail($buyerId),
            'dealer' => Dealer::query()->findOrFail($buyerId),
        };
    }
}
