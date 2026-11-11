<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImpersonateUserRequest;
use App\Models\Tenant;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;

/**
 * Platform_Admin impersonation endpoint (R8.6, R34.3).
 *
 * Lives under the `platform.admin` route group, which runs WITHOUT tenancy
 * initialization and operates against the central database (R1.6). This is a
 * thin controller: it validates the payload via {@see ImpersonateUserRequest},
 * resolves the target {@see Tenant} centrally, and delegates all orchestration
 * (initialize the target tenant DB, verify the target user, issue a short-lived
 * scoped token, audit the session) to {@see ImpersonationService}.
 *
 * A missing target user surfaces as HTTP 404 via the service's
 * NotFoundHttpException and the centralized error renderer.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    /**
     * POST /api/admin/impersonate/{userId} — begin an impersonation session
     * for the given tenant user (R8.6, R34.3).
     */
    public function store(ImpersonateUserRequest $request, int|string $userId): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($request->validated('tenant_id'));

        $result = $this->impersonation->impersonate(
            $tenant,
            $userId,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'abilities' => [ImpersonationService::IMPERSONATION_ABILITY],
            'expires_at' => $result['expires_at']->toIso8601String(),
            'tenant_id' => $result['tenant_id'],
            'target_user' => $result['target_user'],
        ], 201);
    }
}
