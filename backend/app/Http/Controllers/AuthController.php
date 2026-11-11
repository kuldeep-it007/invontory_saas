<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentication endpoints (R7). Thin controller: validates input and delegates
 * all logic to {@see AuthService}. All actions run against the active tenant
 * database (resolved by the tenancy middleware before the request reaches here).
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * POST /api/login — authenticate and issue a Sanctum token (R7.1).
     *
     * Invalid credentials surface as HTTP 401 via the AuthenticationException
     * thrown in the service and the centralized error renderer (R7.2).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $result = $this->auth->login(
            $data['login'],
            $data['password'],
            $data['device_name'] ?? null,
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => $this->identity($result['user']),
        ]);
    }

    /**
     * POST /api/logout — revoke the current token (R7.6).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * POST /api/forgot-password — email a password-reset link (R7.4).
     *
     * Returns a uniform message regardless of whether the email is registered,
     * to avoid disclosing account existence.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->auth->sendPasswordResetLink($data['email']);

        return response()->json([
            'message' => 'If the email is registered, a password reset link has been sent.',
        ]);
    }

    /**
     * GET /api/me — the authenticated user's identity, roles, and abilities (R8.5).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->identity($request->user()));
    }

    /**
     * Build the identity payload: who the user is, the roles they hold, the
     * flat ability set those roles grant (R8.5), and — for a buyer-side panel
     * user — the Distributor/Dealer party it is linked to (R8.6). The frontend
     * uses the party block to auto-scope the panel (orders/cart) to the logged
     * in user's own party without any impersonation.
     *
     * @return array<string, mixed>
     */
    private function identity(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'abilities' => $user->abilities(),
            'distributor_id' => $user->distributor_id,
            'dealer_id' => $user->dealer_id,
            'party' => $this->party($user),
        ];
    }

    /**
     * The buyer party (Distributor or Dealer) the user logs in as, as a compact
     * {type, id, name} block — or null for a company-side user with no party
     * link (R8.6). Mirrors {@see User::party()} so the panel knows whose data to
     * show for a logged-in distributor/dealer user.
     *
     * @return array{type: "distributor"|"dealer", id: int, name: string}|null
     */
    private function party(User $user): ?array
    {
        $party = $user->party();

        if ($party === null) {
            return null;
        }

        return [
            'type' => $user->dealer_id !== null ? 'dealer' : 'distributor',
            'id' => (int) $party->getKey(),
            'name' => (string) $party->name,
        ];
    }
}
