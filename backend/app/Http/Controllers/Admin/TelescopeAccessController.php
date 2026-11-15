<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Auth\Roles;
use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Bridges the platform console (bearer token) to Telescope (browser session).
 *
 * The console asks for a link; the link is signed, valid for 60 seconds and
 * usable once. Opening it starts a web session for that Platform_Admin on the
 * platform domain only, then redirects to Telescope. Both steps are audited.
 */
class TelescopeAccessController extends Controller
{
    private const TTL_SECONDS = 60;

    /** POST /api/admin/telescope/session (Platform_Admin) */
    public function issue(Request $request): JsonResponse
    {
        $nonce = Str::random(40);
        Cache::put('telescope-link:'.$nonce, $request->user()->getKey(), self::TTL_SECONDS + 30);

        PlatformAuditLog::record('telescope.link_issued', $request);

        return response()->json(['data' => [
            'url' => URL::temporarySignedRoute('telescope.enter', now()->addSeconds(self::TTL_SECONDS), ['nonce' => $nonce]),
            'expires_in' => self::TTL_SECONDS,
        ]]);
    }

    /** GET /api/telescope-enter?nonce=…&signature=… (web, signed) */
    public function enter(Request $request): RedirectResponse
    {
        $userId = Cache::pull('telescope-link:'.(string) $request->query('nonce'));
        $user = $userId !== null ? User::query()->find($userId) : null;

        if ($user === null || ! $user->hasRole(Roles::PLATFORM_ADMIN)) {
            abort(403, 'This Telescope link is invalid or has already been used.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        PlatformAuditLog::record('telescope.opened', $request, null, [], ['actor_id' => $user->getKey()]);

        return redirect('/'.trim((string) config('telescope.path'), '/'));
    }
}
