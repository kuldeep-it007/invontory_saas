<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform_Admin tenant master-config CRUD (R2, R1).
 *
 * Runs on the central `platform.admin` pipeline (no tenancy). Provisioning
 * (create) lives in {@see TenantProvisioningController}; this controller covers
 * list / show / update / delete of the central tenant registry. Deleting a
 * tenant fires stancl/tenancy's TenantDeleted pipeline, which drops the
 * per-tenant database — a destructive operation, so it is Platform_Admin-only.
 */
class TenantAdminController extends Controller
{
    /** GET /api/admin/tenants — paginated tenant list with plan/subscription summary. */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $tenants = Tenant::query()
            ->with(['domains:id,tenant_id,domain'])
            ->orderBy('subdomain')
            ->paginate($perPage);

        // Attach the latest subscription summary per tenant (central tables).
        $data = $tenants->getCollection()->map(fn (Tenant $t) => $this->summarize($t));
        $tenants->setCollection($data);

        return response()->json($tenants);
    }

    /** GET /api/admin/tenants/{tenant} — a single tenant with its domains + subscription. */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('domains:id,tenant_id,domain');

        return response()->json(['data' => $this->summarize($tenant)]);
    }

    /** PATCH /api/admin/tenants/{tenant} — update tenant business toggles. */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['sometimes', 'string', 'size:3'],
            'backorders_enabled' => ['sometimes', 'boolean'],
            'dealer_stock_enabled' => ['sometimes', 'boolean'],
        ]);

        $tenant->fill($data)->save();

        return response()->json(['data' => $this->summarize($tenant->fresh('domains'))]);
    }

    /** DELETE /api/admin/tenants/{tenant} — remove tenant + drop its database. */
    public function destroy(Tenant $tenant): JsonResponse
    {
        // Deleting the Tenant triggers the TenantDeleted pipeline (DeleteDatabase).
        $tenant->domains()->delete();
        $tenant->subscriptions()->delete();
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted.']);
    }

    /**
     * Build a flat central summary for a tenant: identity, domains, and the
     * latest subscription (plan + status).
     *
     * @return array<string, mixed>
     */
    private function summarize(Tenant $tenant): array
    {
        $subscription = $tenant->subscriptions()->latest('id')->first();

        return [
            'id' => $tenant->getTenantKey(),
            'subdomain' => $tenant->subdomain,
            'currency' => $tenant->currency,
            'backorders_enabled' => (bool) $tenant->backorders_enabled,
            'dealer_stock_enabled' => (bool) $tenant->dealer_stock_enabled,
            'domains' => $tenant->domains->pluck('domain')->all(),
            'subscription' => $subscription ? [
                'id' => $subscription->getKey(),
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
            ] : null,
        ];
    }
}
