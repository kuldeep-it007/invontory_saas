<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\SubmitCartRequest;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\User;
use App\Services\CartSubmissionService;
use App\Services\OrderListService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Order placement HTTP surface (R20).
 *
 * Thin controller: it validates the cart payload via {@see SubmitCartRequest}
 * and delegates all business rules — buyer→seller resolution, per-line pricing,
 * monthly-order quota enforcement, and the single-order/one-line-per-entry
 * creation — to {@see CartSubmissionService} (R20.1–R20.4). It runs on the
 * tenant pipeline, so every action is confined to the active tenant database.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly CartSubmissionService $cart,
        private readonly OrderListService $orders,
    ) {}

    /**
     * GET /api/orders — order history, scoped to the viewer (R20, R33).
     *
     * Query params:
     *   • no party                → the Company's full history (every order);
     *   • party_type/party_id + scope=buyer  → orders that party placed;
     *   • party_type/party_id + scope=seller → orders placed against that party
     *     (a Distributor's "dealer orders"). A seller scope with no party_id
     *     targets the tenant Company so a Company viewer can list what it sold.
     */
    public function index(ListOrdersRequest $request): JsonResponse
    {
        $data = $request->validated();
        $perPage = (int) ($data['per_page'] ?? 15);
        $scope = $data['scope'] ?? null;
        // Optional filters: status may be a comma-separated list.
        $filters = [
            'status' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['status'] ?? ''))))),
            'order_type' => $data['order_type'] ?? null,
        ];
        $partyType = $data['party_type'] ?? null;
        $partyId = isset($data['party_id']) ? (int) $data['party_id'] : null;

        // A Distributor/Dealer PANEL user may only ever see its OWN party's
        // orders: force the party context to the user's linked party and reject
        // any attempt to address another party (R8.6, R28.10). Company-side
        // users (no party link) keep the full oversight behaviour below.
        $this->confinePanelUserToOwnParty($request->user(), $partyType, $partyId);

        // No party context → the Company's full tenant history.
        if ($partyType === null && $scope === null) {
            return response()->json($this->orders->all($perPage, $filters));
        }

        if ($scope === 'seller') {
            // "Orders placed against me." A party-less seller scope means the
            // tenant Company (its distributors' orders against the company).
            if ($partyType === null || $partyType === 'company') {
                $companyId = $partyId ?? (int) Company::query()->value('id');

                return response()->json($this->orders->soldBy('company', $companyId, $perPage, $filters));
            }

            return response()->json($this->orders->soldBy($partyType, (int) $partyId, $perPage, $filters));
        }

        // "Orders placed by" (buyer). With a specific party → that party's
        // orders; with only a party_type → every order placed by that tier
        // (e.g. all distributor orders for the Company's oversight).
        if ($partyType !== null && $partyId !== null) {
            return response()->json($this->orders->placedBy($partyType, $partyId, $perPage, $filters));
        }

        if ($partyType !== null) {
            return response()->json($this->orders->placedByType($partyType, $perPage, $filters));
        }

        return response()->json($this->orders->all($perPage, $filters));
    }

    /**
     * GET /api/orders/{order} — a single order with its lines and parties.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        // A Distributor/Dealer panel user may only open orders it placed (buyer)
        // or received (seller), mirroring the listing's confinement (R28.10).
        $own = $request->user()?->reportBuyerBinding();
        if ($own !== null) {
            $isBuyer = $order->buyer_type === $own[0] && (int) $order->buyer_id === $own[1];
            $isSeller = $order->seller_type === $own[0] && (int) $order->seller_id === $own[1];
            if (! $isBuyer && ! $isSeller) {
                throw new AccessDeniedHttpException('You can only view your own orders.');
            }
        }

        // Full product detail per line (category › collection › colour) and the
        // stock points each line is sourced from, for the order view.
        $order->load([
            'buyer',
            'seller',
            'lines.product.collection.category',
            'lines.product.colour',
            'lines.allocations.stockPoint',
        ]);

        return response()->json(['data' => $order]);
    }

    /**
     * POST /api/orders — submit a cart, creating one Order with one Order_Line
     * per cart entry (R20.3, R20.4).
     *
     * The Buyer (Distributor/Dealer) determines the Seller the order is placed
     * against (R20.1, R20.2); the service prices each line and enforces the
     * monthly order quota before creating the order. Returns the created order
     * with its lines.
     */
    public function store(SubmitCartRequest $request): JsonResponse
    {
        $data = $request->validated();

        $buyer = $this->resolveBuyer($data['buyer_type'], (int) $data['buyer_id']);

        $order = $this->cart->submit(
            $buyer,
            $data['lines'],
            $data['order_type'] ?? 'standard',
        );

        return response()->json(['data' => $order], 201);
    }

    /**
     * Confine a Distributor/Dealer panel user to its OWN party for the order
     * listing (R8.6, R28.10). If the user is linked to a party, the requested
     * `party_type`/`party_id` are forced to that party; a request that names a
     * DIFFERENT party is rejected with a 403 so one buyer can never read
     * another buyer's orders. Company-side users (no party link) are untouched
     * and keep full oversight.
     *
     * @param  string|null  $partyType  Forced to the user's party type when linked.
     * @param  int|null  $partyId  Forced to the user's party id when linked.
     */
    private function confinePanelUserToOwnParty(?User $user, ?string &$partyType, ?int &$partyId): void
    {
        if ($user === null) {
            return;
        }

        $ownType = null;
        $ownId = null;
        if ($user->dealer_id !== null) {
            $ownType = 'dealer';
            $ownId = (int) $user->dealer_id;
        } elseif ($user->distributor_id !== null) {
            $ownType = 'distributor';
            $ownId = (int) $user->distributor_id;
        }

        // Company-side user (no party link): no confinement.
        if ($ownType === null) {
            return;
        }

        // Reject an explicit attempt to address a different party.
        if (($partyType !== null && $partyType !== $ownType)
            || ($partyId !== null && $partyId !== $ownId)) {
            throw new AccessDeniedHttpException('You can only view your own orders.');
        }

        // Force the party context to the user's own party.
        $partyType = $ownType;
        $partyId = $ownId;
    }

    /**
     * Resolve the Buyer model from the request's morph type + id (R20.1,
     * R20.2). The type is already restricted to `distributor`/`dealer` by the
     * form request; the record must exist in the active tenant database.
     */
    private function resolveBuyer(string $buyerType, int $buyerId): Model
    {
        return match ($buyerType) {
            'distributor' => Distributor::query()->findOrFail($buyerId),
            'dealer' => Dealer::query()->findOrFail($buyerId),
        };
    }
}
