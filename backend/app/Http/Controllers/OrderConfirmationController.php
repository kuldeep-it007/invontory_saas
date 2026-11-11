<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\OrderConfirmationService;
use App\Services\OrderLifecycleAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Order confirmation endpoint (R16, R21.3, R21.5, R27.3).
 *
 * Thin controller: it resolves the {@see Order} from the route binding (404
 * when it does not exist in the active tenant database) and delegates the whole
 * confirmation — the FOR UPDATE stock movements (R16), the credit-limit check
 * (R21.3), the synchronous invoice generation under the seller's own series
 * (R21.5), the buyer account-ledger purchase (R25.1), the Confirmed transition,
 * and the queued order-confirmed notification (R27.3) — to
 * {@see OrderConfirmationService}. All business rules live in the service, which
 * runs the whole pipeline inside ONE tenant-database transaction so a failure
 * leaves the order unchanged.
 *
 * An illegal `confirm` from the order's current state, an oversell with
 * backorders off, or a credit-limit breach are each rejected by the service
 * with a 422 (mapped to the standard error envelope by the centralized
 * {@see \App\Exceptions\ApiExceptionRenderer}); because the service asserts
 * legality and stock/credit BEFORE committing, the order is left completely
 * unchanged.
 *
 * Runs on the tenant pipeline, so the order, its invoice, and its ledgers
 * belong to the active tenant database.
 */
class OrderConfirmationController extends Controller
{
    public function __construct(
        private readonly OrderConfirmationService $confirmation,
        private readonly OrderLifecycleAuthorizer $authorizer,
    ) {}

    /**
     * POST /api/orders/{order}/confirm — confirm a placed order (R16, R21.3,
     * R21.5).
     *
     * Returns the confirmed order with its lines and the invoice the
     * confirmation issued under the seller's own series (R21.5).
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        // Only the order's seller (the owner of the stock it draws from) may
        // confirm it (R21.3).
        $this->authorizer->assertCanManage($request->user(), $order);

        $confirmed = $this->confirmation->confirm($order->load('lines'), [
            'actor_id' => $request->user()?->getKey(),
        ]);

        $invoice = Invoice::query()
            ->where('order_id', (int) $confirmed->getKey())
            ->first();

        return response()->json([
            'data' => [
                'order' => $confirmed->load('lines'),
                'invoice' => $invoice,
            ],
        ]);
    }
}
