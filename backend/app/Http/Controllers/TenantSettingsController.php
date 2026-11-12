<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WhiteLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * White-label tenant settings endpoints (R3). Thin controller: validates input
 * and delegates all logic to {@see WhiteLabelService}. Both actions run against
 * the active tenant database (resolved by the tenancy middleware before the
 * request reaches here), so settings read/written belong to the current tenant.
 */
class TenantSettingsController extends Controller
{
    public function __construct(private readonly WhiteLabelService $whiteLabel) {}

    /**
     * GET /api/tenant/settings — the active tenant's white-label settings (R3.1).
     *
     * Returns the full, coherent settings object (logo, brand name, colour
     * theme, favicon, custom domain, outbound sender identity); unset keys are
     * null so the shape is stable for the frontend theme loader (R3.2).
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->whiteLabel->get()]);
    }

    /**
     * GET /api/tenant/branding — PUBLIC display branding for the tenant resolved
     * from the request host (brand name, logo, favicon, colour theme). No token
     * is required: the login page and the first server render need it before
     * anyone is signed in. Never includes mail or domain settings.
     */
    public function branding(): JsonResponse
    {
        return response()
            ->json(['data' => $this->whiteLabel->publicBranding()])
            // Short shared cache: branding rarely changes and every page load
            // asks for it; an update is visible within a minute.
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * PATCH /api/tenant/settings — update white-label settings (R3.5).
     *
     * Authorized to Company_Admin via route middleware. A partial payload is
     * accepted; the service applies the update and records it in the audit log,
     * applying the update regardless of whether the audit write succeeds (R3.5).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color_theme' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $this->whiteLabel->update($data)]);
    }
}
