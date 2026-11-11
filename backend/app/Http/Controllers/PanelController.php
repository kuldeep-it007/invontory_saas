<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\User;
use App\Services\OrderListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The buyer-side PANEL: what a logged-in Distributor or Dealer user sees about
 * ITSELF (R8.6, R20.1, R20.2, R28.10).
 *
 * Unlike the Company-side order listing (where the viewer may pass a party
 * context), the panel derives the party STRICTLY from the authenticated user's
 * link ({@see User::party()} / {@see User::reportBuyerBinding()}). A panel user
 * can therefore only ever see its OWN details and orders — it cannot address
 * another party by passing query params. A user with no party link (a
 * company-side user) is not a panel user and gets a 403.
 *
 * Order scoping (via {@see OrderListService}):
 *   • "orders placed"   → orders where the party is the BUYER (R20.1, R20.2).
 *   • "orders received" → orders where the party is the SELLER; meaningful for
 *     a Distributor (its dealers' orders against its stock). A Dealer holds no
 *     stock and sells to no one, so its received list is always empty.
 */
class PanelController extends Controller
{
    public function __construct(private readonly OrderListService $orders) {}

    /**
     * GET /api/panel/me — the authenticated panel user's party details (R8.6).
     *
     * Returns the Distributor or Dealer the user logs in as, with the dealer's
     * serving distributor loaded for context. A non-panel user gets a 403.
     */
    public function me(Request $request): JsonResponse
    {
        $party = $this->requirePanelParty($request->user());

        if ($party instanceof Dealer) {
            $party->load('distributor:id,name');
        }

        return response()->json([
            'party_type' => $this->partyType($party),
            'data' => $party,
        ]);
    }

    /**
     * GET /api/panel/orders/placed — orders this party PLACED as the buyer
     * (R20.1, R20.2). A distributor sees its orders against the Company; a
     * dealer sees its orders against its serving distributor.
     */
    public function ordersPlaced(Request $request): JsonResponse
    {
        $party = $this->requirePanelParty($request->user());
        $perPage = $this->perPage($request);

        return response()->json(
            $this->orders->placedBy($this->partyType($party), (int) $party->getKey(), $perPage),
        );
    }

    /**
     * GET /api/panel/orders/received — orders placed AGAINST this party as the
     * seller (R20.2). Meaningful for a Distributor (its dealers' orders). A
     * Dealer is never a seller, so its received list is empty.
     */
    public function ordersReceived(Request $request): JsonResponse
    {
        $party = $this->requirePanelParty($request->user());
        $perPage = $this->perPage($request);

        // Only a Distributor can be a seller; a Dealer receives no orders.
        if (! $party instanceof Distributor) {
            return response()->json($this->orders->soldBy('distributor', -1, $perPage));
        }

        return response()->json(
            $this->orders->soldBy('distributor', (int) $party->getKey(), $perPage),
        );
    }

    /**
     * Resolve the authenticated user's buyer party, or abort 403 when the user
     * is not a panel user (no distributor/dealer link). This is the single
     * place the panel's "only your own data" rule is enforced.
     */
    private function requirePanelParty(User $user): Distributor|Dealer
    {
        $party = $user->party();

        abort_if(
            $party === null,
            403,
            'This account is not linked to a distributor or dealer panel.',
        );

        return $party;
    }

    /**
     * The short party type ("distributor" | "dealer") for a resolved party.
     *
     * @return "distributor"|"dealer"
     */
    private function partyType(Distributor|Dealer $party): string
    {
        return $party instanceof Distributor ? 'distributor' : 'dealer';
    }

    /**
     * The clamped page size for a panel listing.
     */
    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->integer('per_page', 15), 100));
    }
}
