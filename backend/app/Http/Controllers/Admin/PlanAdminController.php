<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform_Admin plan master-config CRUD (R4).
 *
 * Plans, their quota limits, and feature flags are central cross-tenant
 * configuration. Runs on the `platform.admin` pipeline (no tenancy). Limits and
 * features are managed inline with the plan so the superadmin edits a plan's
 * full shape in one place (R4.2, R4.5).
 */
class PlanAdminController extends Controller
{
    /** GET /api/admin/plans — all plans with limits + features. */
    public function index(): JsonResponse
    {
        // Tenants currently on each plan (latest subscription per tenant).
        $counts = Subscription::query()
            ->whereIn('id', Subscription::query()->selectRaw('MAX(id)')->groupBy('tenant_id'))
            ->selectRaw('plan_id, COUNT(*) as c')->groupBy('plan_id')->pluck('c', 'plan_id');

        $plans = Plan::query()->with(['limits', 'features'])->orderBy('id')->get()
            ->each(fn (Plan $p) => $p->setAttribute('tenants_count', (int) ($counts[$p->getKey()] ?? 0)));

        return response()->json(['data' => $plans]);
    }

    /** GET /api/admin/plans/{plan} */
    public function show(Plan $plan): JsonResponse
    {
        return response()->json(['data' => $plan->load(['limits', 'features'])]);
    }

    /** POST /api/admin/plans — create a plan (+ optional limits/features). */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);

        $plan = Plan::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'billing_cycle' => $data['billing_cycle'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->syncLimits($plan, $data['limits'] ?? []);
        $this->syncFeatures($plan, $data['features'] ?? []);

        PlatformAuditLog::record('plan.created', $request, null, [], $this->snapshot($plan->load(['limits', 'features'])));

        return response()->json(['data' => $plan->load(['limits', 'features'])], 201);
    }

    /** PATCH /api/admin/plans/{plan} — update plan + its limits/features. */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $this->validatePlan($request, updating: true);
        $before = $this->snapshot($plan->load(['limits', 'features']));

        $plan->fill(array_filter(
            [
                'name' => $data['name'] ?? null,
                'price' => $data['price'] ?? null,
                'billing_cycle' => $data['billing_cycle'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ],
            static fn ($v) => $v !== null,
        ))->save();

        if (array_key_exists('limits', $data)) {
            $this->syncLimits($plan, $data['limits']);
        }
        if (array_key_exists('features', $data)) {
            $this->syncFeatures($plan, $data['features']);
        }

        $fresh = $plan->fresh(['limits', 'features']);
        $after = $this->snapshot($fresh);
        if ($after !== $before) {
            PlatformAuditLog::record('plan.updated', $request, null, $before, $after);
        }

        return response()->json(['data' => $fresh]);
    }

    /** DELETE /api/admin/plans/{plan} — delete a plan (blocked if in use). */
    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'PLAN_IN_USE',
                    'message' => 'This plan has active subscriptions and cannot be deleted.',
                ],
            ], 409);
        }

        PlatformAuditLog::record('plan.deleted', $request, null, $this->snapshot($plan->load(['limits', 'features'])));
        $plan->limits()->delete();
        $plan->features()->delete();
        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }

    /** @return array<string, mixed> */
    private function snapshot(Plan $plan): array
    {
        return [
            'name' => $plan->name,
            'price' => (string) $plan->price,
            'billing_cycle' => $plan->billing_cycle,
            'is_active' => (bool) $plan->is_active,
            'limits' => $plan->limits->sortBy('key')->pluck('value', 'key')->all(),
            'features' => $plan->features->sortBy('key')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlan(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:255'],
            'price' => [$req, 'numeric', 'min:0'],
            'billing_cycle' => [$req, Rule::in(['monthly', 'yearly'])],
            'is_active' => ['sometimes', 'boolean'],
            'limits' => ['sometimes', 'array'],
            'limits.*.key' => ['required_with:limits', 'string', 'max:64'],
            'limits.*.value' => ['nullable', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*.key' => ['required_with:features', 'string', 'max:64'],
            'features.*.enabled' => ['required_with:features', 'boolean'],
        ]);
    }

    /**
     * @param  array<int, array{key: string, value: int|null}>  $limits
     */
    private function syncLimits(Plan $plan, array $limits): void
    {
        foreach ($limits as $limit) {
            $plan->limits()->updateOrCreate(
                ['key' => $limit['key']],
                ['value' => $limit['value'] ?? null],
            );
        }
    }

    /**
     * @param  array<int, array{key: string, enabled: bool}>  $features
     */
    private function syncFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $feature) {
            $plan->features()->updateOrCreate(
                ['key' => $feature['key']],
                ['enabled' => (bool) $feature['enabled']],
            );
        }
    }
}
