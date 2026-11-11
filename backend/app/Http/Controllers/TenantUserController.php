<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ResetPartyUserPasswordRequest;
use App\Http\Requests\StorePartyUserRequest;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\User;
use App\Services\TenantUserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buyer-side PANEL login user management (R7.1, R8.6, R10.3).
 *
 * Thin controller: validates input and delegates all business rules to
 * {@see TenantUserService}. Users are created nested under a route-model-bound
 * Distributor or Dealer so the owning party is unambiguous and cannot be
 * spoofed via the body. Listing is column-scoped so the login identity is never
 * exposed beyond id/name/email. Every action runs against the active tenant
 * database; creation is gated by `users.manage` + `subscription.active` and the
 * `max_users` quota (enforced inside the service).
 */
class TenantUserController extends Controller
{
    public function __construct(private readonly TenantUserService $users) {}

    /**
     * GET /api/distributors/{distributor}/users — the distributor's panel
     * users. Filtered on the indexed `distributor_id` FK column.
     */
    public function indexForDistributor(Request $request, Distributor $distributor): JsonResponse
    {
        return response()->json($this->paginateUsers(
            User::query()->where('distributor_id', (int) $distributor->getKey()),
            $request,
        ));
    }

    /**
     * POST /api/distributors/{distributor}/users — create a login user for the
     * distributor's panel (assigns the Distributor role, links the party).
     */
    public function storeForDistributor(StorePartyUserRequest $request, Distributor $distributor): JsonResponse
    {
        $user = $this->users->createForDistributor($distributor, $request->validated());

        return response()->json(['data' => $this->identity($user)], 201);
    }

    /**
     * GET /api/distributors/{distributor}/dealers/{dealer}/users — the dealer's
     * panel users. The dealer must belong to the addressed distributor.
     */
    public function indexForDealer(Request $request, Distributor $distributor, Dealer $dealer): JsonResponse
    {
        $this->assertDealerBelongsToDistributor($distributor, $dealer);

        return response()->json($this->paginateUsers(
            User::query()->where('dealer_id', (int) $dealer->getKey()),
            $request,
        ));
    }

    /**
     * POST /api/distributors/{distributor}/dealers/{dealer}/users — create a
     * login user for the dealer's panel (assigns the Dealer role, links the
     * party). The dealer must belong to the addressed distributor.
     */
    public function storeForDealer(StorePartyUserRequest $request, Distributor $distributor, Dealer $dealer): JsonResponse
    {
        $this->assertDealerBelongsToDistributor($distributor, $dealer);

        $user = $this->users->createForDealer($dealer, $request->validated());

        return response()->json(['data' => $this->identity($user)], 201);
    }

    /**
     * PATCH /api/distributors/{distributor}/users/{user}/password — the Company
     * resets a distributor panel user's login password (R7.3). Gated by
     * `users.manage` (Company-only). The target user must belong to the
     * addressed distributor, else 404.
     */
    public function resetDistributorUserPassword(
        ResetPartyUserPasswordRequest $request,
        Distributor $distributor,
        User $user,
    ): JsonResponse {
        abort_unless(
            (int) $user->distributor_id === (int) $distributor->getKey(),
            404,
            'The requested user was not found for this distributor.',
        );

        $user = $this->users->resetPassword($user, (string) $request->validated()['password']);

        return response()->json(['data' => $this->identity($user)]);
    }

    /**
     * PATCH /api/distributors/{distributor}/dealers/{dealer}/users/{user}/password
     * — reset a dealer panel user's login password (R7.3). Gated by
     * `users.manage|dealers.manage` so the Company OR the owning Distributor can
     * do it. The dealer must belong to the addressed distributor and the user
     * must belong to the addressed dealer, else 404.
     */
    public function resetDealerUserPassword(
        ResetPartyUserPasswordRequest $request,
        Distributor $distributor,
        Dealer $dealer,
        User $user,
    ): JsonResponse {
        $this->assertDealerBelongsToDistributor($distributor, $dealer);

        abort_unless(
            (int) $user->dealer_id === (int) $dealer->getKey(),
            404,
            'The requested user was not found for this dealer.',
        );

        $user = $this->users->resetPassword($user, (string) $request->validated()['password']);

        return response()->json(['data' => $this->identity($user)]);
    }

    /**
     * Paginate a users query, selecting only the safe identity columns (never
     * the password hash) and ordering by name for a stable page.
     *
     * @param  Builder<User>  $query
     */
    private function paginateUsers(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return $query
            ->select(['id', 'name', 'email', 'distributor_id', 'dealer_id', 'created_at'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * The safe identity payload for a created user — never the password hash.
     *
     * @return array<string, mixed>
     */
    private function identity(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'distributor_id' => $user->distributor_id,
            'dealer_id' => $user->dealer_id,
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }

    /**
     * Guard the nested relationship: a dealer addressed under a distributor
     * must actually belong to it, else 404 (prevents cross-distributor access).
     */
    private function assertDealerBelongsToDistributor(Distributor $distributor, Dealer $dealer): void
    {
        abort_unless(
            (int) $dealer->distributor_id === (int) $distributor->getKey(),
            404,
            'The requested dealer was not found for this distributor.',
        );
    }
}
