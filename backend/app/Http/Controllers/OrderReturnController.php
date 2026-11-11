<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OrderReturnRequest;
use App\Models\Order;
use App\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;

/**
 * Order return endpoint (R18.3, R22.2, R22.3).
 *
 * Thin controller: it validates the return payload via {@see OrderReturnRequest},
 * resolves the {@see Order} from the route binding (404 when it does not exist
 * in the active tenant database), and delegates the whole return — re-entering
 * the returned quantities into stock, adjusting the buyer's account via the
 * transaction ledger, and recomputing the Outstanding_Balance — to
 * {@see OrderReturnService}. All business rules live in the service.
 *
 * A return line for a product not on the order (or a non-positive quantity) is
 * rejected by the service with a 422
 * {@see \App\Exceptions\InvalidReturnException}, mapped to the standard error
 * envelope by the centralized {@see \App\Exceptions\ApiExceptionRenderer}.
 *
 * Runs on the tenant pipeline, so the order and its ledgers belong to the
 * active tenant database.
 */
class OrderReturnController extends Controller
{
    public function __construct(private readonly OrderReturnService $returns) {}

    /**
     * POST /api/orders/{order}/return — process a return against a confirmed
     * order (R18.3, R22.2, R22.3).
     *
     * Returns a summary of the return including the recomputed
     * Outstanding_Balance (R22.3).
     */
    public function store(OrderReturnRequest $request, Order $order): JsonResponse
    {
        $summary = $this->returns->process(
            $order->load('lines'),
            $request->validated()['lines'],
            ['actor_id' => $request->user()?->getKey()],
        );

        return response()->json(['data' => $summary]);
    }
}
