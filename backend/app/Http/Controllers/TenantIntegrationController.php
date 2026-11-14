<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TenantIntegrations\TenantIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-level SMTP and object-storage settings (Company_Admin only, via the
 * route). Thin: validation rules and all behaviour live in
 * {@see TenantIntegrationService}. `{type}` is `smtp` or `storage`.
 *
 * Responses never contain a password or secret key; see the service.
 */
class TenantIntegrationController extends Controller
{
    public function __construct(private readonly TenantIntegrationService $integrations) {}

    /** GET /api/tenant/integrations */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->integrations->overview()]);
    }

    /** PUT /api/tenant/integrations/{type} - test, then save. */
    public function update(Request $request, string $type): JsonResponse
    {
        $input = $request->validate($this->integrations->rules($type));

        return response()->json(['data' => $this->integrations->save($type, $input, $request->user())]);
    }

    /** POST /api/tenant/integrations/{type}/test - dry run, nothing is saved. */
    public function test(Request $request, string $type): JsonResponse
    {
        $input = $request->validate($this->integrations->rules($type));

        return response()->json(['data' => $this->integrations->test($type, $input, $request->user())]);
    }

    /** DELETE /api/tenant/integrations/{type} - back to the platform defaults. */
    public function destroy(Request $request, string $type): JsonResponse
    {
        $this->integrations->remove($type, $request->user());

        return response()->json(null, 204);
    }
}
