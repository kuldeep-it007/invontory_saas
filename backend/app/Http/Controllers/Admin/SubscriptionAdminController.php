<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform_Admin subscription master-config (R4.6, R6).
 *
 * Central cross-tenant configuration: assign/change a tenant's plan and manage
 * its lifecycle status. Runs on the `platform.admin` pipeline (no tenancy).
 * This is the master view across ALL tenants; the per-tenant usage-protected
 * plan change (R6.2) is a separate tenant-side concern.
 */
class SubscriptionAdminController extends Controller
{
    private const STATUSES = ['active', 'past_due', 'cancelled', 'trial'];

    /** GET /api/admin/subscriptions — all subscriptions with plan + tenant. */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $query = Subscription::query()
            ->with(['plan:id,name,price,billing_cycle'])
            ->orderByDesc('id');
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', (string) $request->query('tenant_id'));
        }

        return response()->json($query->paginate($perPage));
    }

    /** POST /api/admin/subscriptions — assign a plan to a tenant. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'string', 'exists:central.tenants,id'],
            'plan_id' => ['required', 'integer', 'exists:central.plans,id'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $data['tenant_id'],
            'plan_id' => $data['plan_id'],
            'status' => $data['status'] ?? Subscription::STATUS_ACTIVE,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        PlatformAuditLog::record('subscription.created', $request, $subscription->tenant_id, [], [
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
        ]);

        return response()->json(['data' => $subscription->load('plan')], 201);
    }

    /** PATCH /api/admin/subscriptions/{subscription} — change plan/status. */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['sometimes', 'integer', 'exists:central.plans,id'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $before = $subscription->only(array_keys($data));

        // A plan change goes through SubscriptionService so the usage-based
        // downgrade protection applies (use POST /tenants/{id}/plan to force).
        if (isset($data['plan_id']) && (int) $data['plan_id'] !== (int) $subscription->plan_id) {
            $tenant = Tenant::query()->findOrFail($subscription->tenant_id);
            $target = Plan::query()->findOrFail($data['plan_id']);
            $subscription = app(SubscriptionService::class)->changePlan($tenant, $target, false, $request->user());
        }
        unset($data['plan_id']);
        $subscription->fill($data)->save();

        $after = $subscription->fresh()->only(array_keys($before));
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));
        if ($changed !== []) {
            PlatformAuditLog::record(
                isset($before['plan_id']) && in_array('plan_id', $changed, true)
                    ? 'subscription.plan_changed' : 'subscription.status_changed',
                $request,
                $subscription->tenant_id,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)),
            );
        }

        return response()->json(['data' => $subscription->fresh('plan')]);
    }
}
