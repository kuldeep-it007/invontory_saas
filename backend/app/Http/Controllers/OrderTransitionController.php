<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OrderTransitionRequest;
use App\Models\Order;
use App\Services\OrderLifecycleAuthorizer;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;

/**
 * Order lifecycle transition endpoint (R21.2, R21.4).
 *
 * Thin controller: it validates the payload via {@see OrderTransitionRequest},
 * resolves the {@see Order} from the route binding (404 when it does not exist
 * in the active tenant database), and delegates the actual move to the
 * {@see OrderStateMachine} via {@see Order::transition()}. All lifecycle rules
 * live in the state machine, not here.
 *
 * A legal event advances the order to the resolved state and returns it; an
 * event not defined for the order's current state is rejected by the machine
 * with a 422 {@see \App\Exceptions\IllegalOrderTransitionException}, which the
 * centralized {@see \App\Exceptions\ApiExceptionRenderer} maps to the standard
 * error envelope — and because the machine asserts legality BEFORE any save,
 * the order is left completely unchanged (R21.4).
 *
 * Runs on the tenant pipeline, so the order and its audit trail belong to the
 * active tenant database.
 */
class OrderTransitionController extends Controller
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly OrderLifecycleAuthorizer $authorizer,
    ) {}

    /**
     * POST /api/orders/{order}/transition — apply a lifecycle event to an order
     * (R21.2, R21.4).
     *
     * While an order is Placed this covers the seller's approve/reject/edit
     * actions (R21.2); it also drives dispatch/deliver/close and cancellation
     * from the pre-terminal states (R21.1). Returns the order with its new
     * status on a legal transition.
     */
    public function store(OrderTransitionRequest $request, Order $order): JsonResponse
    {
        // Only the order's seller (the owner of the stock it draws from) may
        // change its status (R21.1, R21.2).
        $this->authorizer->assertCanManage($request->user(), $order);

        $event = $request->validated()['event'];

        $newStatus = $order->transition($event, $this->stateMachine);

        return response()->json([
            'data' => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'status' => $newStatus,
                'available_events' => $this->stateMachine->availableEvents($newStatus),
            ],
        ]);
    }
}
