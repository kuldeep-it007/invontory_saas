<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Central (platform) authentication (R7, R8.6).
 *
 * This is the login path for the CENTRAL database — where Platform_Admin
 * superadmins live. It runs on the `platform.public` route group WITHOUT
 * tenancy initialization, so `User` resolves against the central `users` table
 * and the active guard is pinned to `web` for consistent spatie RBAC.
 *
 * The unified frontend login page posts here when the request host is a central
 * domain (apex / app host). When the host is a tenant subdomain, the tenant
 * login path ({@see \App\Http\Controllers\AuthController}) handles it instead.
 * The response shape is identical to tenant login so the frontend can treat
 * both uniformly and route by the returned roles.
 */
class CentralAuthController extends Controller
{
    /**
     * POST /api/platform/login — authenticate a central user (Platform_Admin)
     * and issue a Sanctum token (R7.1). Invalid credentials → 401 (R7.2).
     */
    public function login(Request $request): JsonResponse
    {
        // Pin the guard to web so spatie role checks match the web-guarded
        // roles seeded centrally (mirrors tenant/provisioning guard handling).
        Auth::shouldUse('web');

        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        /** @var User|null $user */
        $user = User::on('central')->where('email', $data['login'])->first();

        $valid = $user !== null && Hash::check($data['password'], $user->getAuthPassword());

        if (! $valid) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'platform-admin',
            $user->abilities(),
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'scope' => 'platform',
            'user' => $this->identity($user),
        ]);
    }

    /**
     * GET /api/platform/me — the authenticated central user (R8.5).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->identity($request->user()));
    }

    /**
     * POST /api/platform/logout — revoke the current central token (R7.6).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'scope' => 'platform',
            'roles' => $user->getRoleNames()->values()->all(),
            'abilities' => $user->abilities(),
        ];
    }
}
