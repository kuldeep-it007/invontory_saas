<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
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

        $subscriptions = Subscription::query()
            ->with(['plan:id,name,price,billing_cycle'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($subscriptions);
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

        $subscription->fill($data)->save();

        return response()->json(['data' => $subscription->fresh('plan')]);
    }
}
