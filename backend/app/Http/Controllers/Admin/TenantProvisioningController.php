<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionTenantRequest;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;

/**
 * Platform_Admin tenant provisioning endpoint (R2).
 *
 * Lives under the `platform.admin` route group, which runs WITHOUT tenancy
 * initialization and operates against the central database (R1.6). This is a
 * thin controller: it validates the payload via {@see ProvisionTenantRequest}
 * and delegates all orchestration to {@see TenantProvisioningService}. A
 * duplicate subdomain surfaces as HTTP 409 via the service's
 * ConflictHttpException and the centralized error renderer (R2.3).
 */
class TenantProvisioningController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $provisioning) {}

    /**
     * POST /api/admin/tenants — provision a new tenant (R2.1, R2.2, R2.3, R2.4).
     */
    public function store(ProvisionTenantRequest $request): JsonResponse
    {
        $result = $this->provisioning->provision(
            $request->validated(),
            $request->user(),
        );

        /** @var \App\Models\Tenant $tenant */
        $tenant = $result['tenant'];
        /** @var \App\Models\Subscription $subscription */
        $subscription = $result['subscription'];

        return response()->json([
            'tenant' => [
                'id' => $tenant->getTenantKey(),
                'subdomain' => $tenant->subdomain,
                'currency' => $tenant->currency,
                'backorders_enabled' => $tenant->backorders_enabled,
                'dealer_stock_enabled' => $tenant->dealer_stock_enabled,
                'domains' => $tenant->domains()->pluck('domain')->all(),
            ],
            'subscription' => [
                'id' => $subscription->getKey(),
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
            ],
            'company_id' => $result['company_id'],
            'admin_id' => $result['admin_id'],
        ], 201);
    }
}
