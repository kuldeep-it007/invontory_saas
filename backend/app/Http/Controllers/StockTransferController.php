<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StockTransferRequest;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;

/**
 * Stock transfer endpoint (R18.1). Thin controller: it validates the payload
 * via {@see StockTransferRequest}, threads the acting user id into the movement
 * context, and delegates every business rule and the atomic
 * decrement/increment-plus-two-ledger-entries operation to
 * {@see StockTransferService}. It runs against the active tenant database
 * (resolved by the tenancy middleware before the request reaches here), so the
 * stock points and ledger entries belong to the current tenant.
 */
class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $transfers) {}

    /**
     * POST /api/stock/transfer — move stock between two stock points of the
     * same owner (R18.1).
     *
     * Authorized by the route pipeline (`permission:inventory.adjust` +
     * `subscription.active`). Returns the two ledger entries written (the
     * `transfer_out` decrement at the source, then the `transfer_in` increment
     * at the destination). A cross-owner transfer is rejected with a 422 by the
     * service; an overdrawn source is rejected with a 422 by the stock
     * authority — in both cases nothing is persisted.
     */
    public function store(StockTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $ledger = $this->transfers->transfer(
            productId: (int) $validated['product_id'],
            sourceStockPointId: (int) $validated['source_stock_point_id'],
            destinationStockPointId: (int) $validated['destination_stock_point_id'],
            quantity: (string) $validated['quantity'],
            context: [
                'actor_id' => $request->user()?->getKey(),
                'reason' => $validated['reason'] ?? null,
                'reference' => $validated['reference'] ?? null,
            ],
        );

        return response()->json(['data' => $ledger], 201);
    }
}
