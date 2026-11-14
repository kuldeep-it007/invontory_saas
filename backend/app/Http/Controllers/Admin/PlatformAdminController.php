<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform_Admin self-service: profile, platform settings/branding, and the
 * central audit trail. Central pipeline (no tenancy); the admin routes are
 * gated by `role:Platform_Admin`. Public branding + media are read-only.
 */
class PlatformAdminController extends Controller
{
    /** Setting keys that may be edited as text. */
    private const TEXT_KEYS = [
        'platform_name', 'tagline', 'legal_name', 'support_email', 'support_phone',
        'website', 'address', 'default_currency', 'timezone', 'primary_color',
    ];

    private const ASSET_KEYS = ['logo', 'favicon'];

    public function __construct(private readonly MediaStorage $media) {}

    /* ── Profile ─────────────────────────────────────────────────────── */

    /** GET /api/admin/profile */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profileData((int) $request->user()->getKey())]);
    }

    /** PATCH /api/admin/profile — name, email, phone and (optionally) password. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255',
                Rule::unique('central.users', 'email')->ignore($user->getKey())],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[0-9+()\-.\s]*$/'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        if (isset($data['password']) && ! Hash::check((string) ($data['current_password'] ?? ''), $user->getAuthPassword())) {
            return response()->json(['error' => [
                'code' => 'INVALID_PASSWORD',
                'message' => 'The current password is incorrect.',
            ]], 422);
        }

        $before = $this->profileData((int) $user->getKey());
        $update = array_intersect_key($data, array_flip(['name', 'email', 'phone']));
        $passwordChanged = isset($data['password']);
        if ($passwordChanged) {
            $update['password'] = Hash::make($data['password']);
        }

        if ($update !== []) {
            // Query builder on purpose: the User model's tenant audit trait
            // would write to a tenant audit table that does not exist centrally.
            $update['updated_at'] = now();
            DB::connection('central')->table('users')->where('id', $user->getKey())->update($update);
        }
        if ($passwordChanged) {
            // Sign out every other session after a password change.
            $user->tokens()->where('id', '!=', $user->currentAccessToken()?->getKey())->delete();
        }

        $after = $this->profileData((int) $user->getKey());
        $changed = array_keys(array_diff_assoc($after, $before));
        if ($changed !== [] || $passwordChanged) {
            PlatformAuditLog::record(
                $passwordChanged ? 'platform.password_changed' : 'platform.profile_updated',
                $request,
                null,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)),
            );
        }

        return response()->json(['data' => $after]);
    }

    /* ── Settings ────────────────────────────────────────────────────── */

    /** GET /api/admin/settings */
    public function settings(): JsonResponse
    {
        return response()->json(['data' => $this->settingsData()]);
    }

    /** PATCH /api/admin/settings */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform_name' => ['sometimes', 'required', 'string', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'support_email' => ['sometimes', 'required', 'email', 'max:255'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[0-9+()\-.\s]*$/'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_currency' => ['sometimes', 'required', 'string', 'size:3', 'alpha'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'primary_color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        if (isset($data['default_currency'])) {
            $data['default_currency'] = strtoupper($data['default_currency']);
        }

        $before = $this->settingsData();
        PlatformSetting::put($data);
        $after = $this->settingsData();

        $changed = array_keys(array_diff_assoc($after, $before));
        if ($changed !== []) {
            PlatformAuditLog::record(
                'platform.settings_updated',
                $request,
                null,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)),
            );
        }

        return response()->json(['data' => $after]);
    }

    /** POST /api/admin/settings/{asset} — upload logo or favicon. */
    public function uploadAsset(Request $request, string $asset): JsonResponse
    {
        abort_unless(in_array($asset, self::ASSET_KEYS, true), 404);

        $rules = $asset === 'logo'
            ? ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=16,min_height=16,max_width=4096,max_height=4096',
                $this->media->contentRule(['png', 'jpg', 'webp'], 'The logo must be a PNG, JPEG or WebP image.')]
            : ['required', 'file', 'mimes:png,ico,webp', 'max:256',
                $this->media->contentRule(['png', 'ico', 'webp'], 'The favicon must be a PNG, ICO or WebP image.')];
        $request->validate(['file' => $rules], [
            'file.mimes' => $asset === 'logo'
                ? 'The logo must be a PNG, JPEG or WebP image.'
                : 'The favicon must be a PNG, ICO or WebP image.',
            'file.max' => $asset === 'logo' ? 'The logo must be smaller than 1 MB.' : 'The favicon must be smaller than 256 KB.',
            'file.dimensions' => 'The logo must be between 16 and 4096 pixels on each side.',
        ]);

        $previous = $this->settingsData()[$asset];
        $url = $this->media->storePlatform($request->file('file'), $asset);
        PlatformSetting::put([$asset => $url]);
        $this->media->deletePlatformUrl($previous);

        PlatformAuditLog::record('platform.asset_updated', $request, null, [$asset => $previous], [$asset => $url]);

        return response()->json(['data' => $this->settingsData()]);
    }

    /** DELETE /api/admin/settings/{asset} */
    public function deleteAsset(Request $request, string $asset): JsonResponse
    {
        abort_unless(in_array($asset, self::ASSET_KEYS, true), 404);

        $previous = $this->settingsData()[$asset];
        PlatformSetting::put([$asset => null]);
        $this->media->deletePlatformUrl($previous);

        PlatformAuditLog::record('platform.asset_removed', $request, null, [$asset => $previous], [$asset => null]);

        return response()->json(['data' => $this->settingsData()]);
    }

    /** GET /api/platform/branding — public name/logo/favicon/colour. */
    public function branding(): JsonResponse
    {
        $s = $this->settingsData();

        return response()
            ->json(['data' => [
                'platform_name' => $s['platform_name'],
                'logo' => $s['logo'],
                'favicon' => $s['favicon'],
                'primary_color' => $s['primary_color'],
            ]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /** GET /api/platform/media/{path} — public platform logo/favicon. */
    public function media(string $path): StreamedResponse
    {
        if (! $this->media->isValidPlatformPath($path)) {
            abort(404);
        }
        $disk = $this->media->disk();
        if (! $disk->exists($path)) {
            abort(404);
        }
        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $this->media->contentType($path),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    /* ── Audit logs ──────────────────────────────────────────────────── */

    /** GET /api/admin/audit-logs?event=&tenant_id=&per_page= */
    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate([
            'event' => ['sometimes', 'string', 'max:64'],
            'tenant_id' => ['sometimes', 'string', 'max:255'],
        ]);
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $query = PlatformAuditLog::query()->orderByDesc('id');
        if ($request->filled('event')) {
            // `subscription` matches every `subscription.*` event.
            $event = (string) $request->query('event');
            $query->where(fn ($q) => $q->where('event', $event)->orWhere('event', 'like', $event.'.%'));
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', (string) $request->query('tenant_id'));
        }

        return response()->json($query->paginate($perPage));
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function profileData(int $id): array
    {
        $row = DB::connection('central')->table('users')->where('id', $id)->first(['id', 'name', 'email', 'phone']);

        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
        ];
    }

    /** @return array<string, string|null> */
    private function settingsData(): array
    {
        $values = PlatformSetting::values([...self::TEXT_KEYS, ...self::ASSET_KEYS]);
        $values['platform_name'] = $values['platform_name'] ?: (string) config('app.name');
        $values['default_currency'] = $values['default_currency'] ?: 'USD';
        $values['timezone'] = $values['timezone'] ?: (string) config('app.timezone', 'UTC');

        return $values;
    }
}
