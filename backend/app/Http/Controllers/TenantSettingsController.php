<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WhiteLabelService;
use App\Services\MediaStorage;
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
     * POST /api/tenant/settings/{asset} — upload the company logo or favicon
     * (multipart field `file`). Stored on the uploads disk (Oracle Object
     * Storage); the setting is updated to the new file's URL and the previous
     * uploaded file, if any, is deleted. Company_Admin only (route).
     */
    public function uploadAsset(Request $request, MediaStorage $media, string $asset): JsonResponse
    {
        $rules = $asset === 'logo'
            ? ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=16,min_height=16,max_width=4096,max_height=4096',
                // The bytes themselves must be an accepted image (not e.g. a renamed SVG).
                $media->contentRule(['png', 'jpg', 'webp'], 'The logo must be a PNG, JPEG or WebP image.')]
            // .ico has no dimension metadata PHP can read, so only type + size.
            : ['required', 'file', 'mimes:png,ico,webp', 'max:256',
                $media->contentRule(['png', 'ico', 'webp'], 'The favicon must be a PNG, ICO or WebP image.')];

        $request->validate(['file' => $rules], [
            'file.mimes' => $asset === 'logo'
                ? 'The logo must be a PNG, JPEG or WebP image.'
                : 'The favicon must be a PNG, ICO or WebP image.',
            'file.max' => $asset === 'logo' ? 'The logo must be smaller than 1 MB.' : 'The favicon must be smaller than 256 KB.',
            'file.dimensions' => 'The logo must be between 16 and 4096 pixels on each side.',
        ]);

        $previous = $this->whiteLabel->get()[$asset] ?? null;
        $url = $media->store($request->file('file'), $asset);
        $settings = $this->whiteLabel->update([$asset => $url]);
        $media->deleteUrl($previous);

        return response()->json(['data' => $settings]);
    }

    /**
     * DELETE /api/tenant/settings/{asset} — remove the logo or favicon (clears
     * the setting and deletes the uploaded file). Company_Admin only (route).
     */
    public function deleteAsset(MediaStorage $media, string $asset): JsonResponse
    {
        $previous = $this->whiteLabel->get()[$asset] ?? null;
        $settings = $this->whiteLabel->update([$asset => null]);
        $media->deleteUrl($previous);

        return response()->json(['data' => $settings]);
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
