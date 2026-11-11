<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderCancellationService;
use App\Services\OrderLifecycleAuthorizer;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Order cancellation endpoint (R18.2, R22.1).
 *
 * Thin controller: it resolves the {@see Order} from the route binding (404
 * when it does not exist in the active tenant database) and delegates the whole
 * cancellation — the stock reversal, the account reversal, and the Cancelled
 * transition — to {@see OrderCancellationService}. All business rules live in
 * the service.
 *
 * An illegal `cancel` from the order's current state is rejected by the state
 * machine inside the service with a 422
 * {@see \App\Exceptions\IllegalOrderTransitionException}, which the centralized
 * {@see \App\Exceptions\ApiExceptionRenderer} maps to the standard error
 * envelope — and because the service asserts legality BEFORE any side effect,
 * the order is left completely unchanged.
 *
 * Runs on the tenant pipeline, so the order and its ledgers belong to the
 * active tenant database.
 */
class OrderCancellationController extends Controller
{
    public function __construct(
        private readonly OrderCancellationService $cancellation,
        private readonly OrderStateMachine $stateMachine,
        private readonly OrderLifecycleAuthorizer $authorizer,
    ) {}

    /**
     * POST /api/orders/{order}/cancel — cancel a confirmed order, reversing its
     * stock movements and account charge (R18.2, R22.1).
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        // Only the order's seller (the owner of the stock it draws from) may
        // cancel it (R22.1).
        $this->authorizer->assertCanManage($request->user(), $order);

        $cancelled = $this->cancellation->cancel($order, [
            'actor_id' => $request->user()?->getKey(),
        ]);

        return response()->json([
            'data' => [
                'id' => $cancelled->getKey(),
                'status' => $cancelled->status,
                'available_events' => $this->stateMachine->availableEvents($cancelled->status),
            ],
        ]);
    }
}
