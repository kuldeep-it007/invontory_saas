<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Platform_Admin tenant master-config (R2, R1).
 *
 * Runs on the central `platform.admin` pipeline (no tenancy). Provisioning
 * (create) lives in {@see TenantProvisioningController}; this controller covers
 * list / show / update / delete of the central tenant registry, plan changes,
 * per-tenant usage and subscription history. Deleting a tenant fires
 * stancl/tenancy's TenantDeleted pipeline, which drops the per-tenant database
 * — a destructive operation, so it is Platform_Admin-only.
 *
 * Contact/billing details are stored in the tenant's central `data` JSON
 * (virtual columns), so no schema change or tenant-database access is needed.
 */
class TenantAdminController extends Controller
{
    /** Editable contact/billing detail fields (stored in tenants.data). */
    private const DETAIL_FIELDS = [
        'company_name', 'contact_name', 'contact_email', 'contact_phone',
        'billing_name', 'billing_email', 'billing_address', 'tax_id', 'notes',
    ];

    /** GET /api/admin/tenants?q=&status= — paginated tenant list with plan/subscription summary. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'past_due', 'cancelled', 'trial'])],
        ]);
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $query = Tenant::query()->with(['domains:id,tenant_id,domain'])->orderBy('subdomain');
        if ($request->filled('q')) {
            $term = addcslashes((string) $request->query('q'), '%_\\');
            $query->where('subdomain', 'like', '%'.$term.'%');
        }
        if ($request->filled('status')) {
            $latest = Subscription::query()->selectRaw('MAX(id)')->groupBy('tenant_id');
            $query->whereIn('id', Subscription::query()
                ->whereIn('id', $latest)
                ->where('status', (string) $request->query('status'))
                ->select('tenant_id'));
        }

        $tenants = $query->paginate($perPage);
        $tenants->setCollection($tenants->getCollection()->map(fn (Tenant $t) => $this->summarize($t)));

        return response()->json($tenants);
    }

    /** GET /api/admin/tenants/{tenant} — tenant details, subscription, plan and usage. */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('domains:id,tenant_id,domain');

        return response()->json(['data' => $this->summarize($tenant, withUsage: true)]);
    }

    /** PATCH /api/admin/tenants/{tenant} — update tenant business toggles and details. */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'backorders_enabled' => ['sometimes', 'boolean'],
            'dealer_stock_enabled' => ['sometimes', 'boolean'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[0-9+()\-.\s]*$/'],
            'billing_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $keys = array_keys($data);
        $before = $this->editableState($tenant, $keys);
        $tenant->fill($data)->save();
        $after = $this->editableState($tenant->fresh(), $keys);

        $changed = array_keys(array_diff_assoc($after, $before));
        if ($changed !== []) {
            PlatformAuditLog::record(
                'tenant.updated',
                $request,
                (string) $tenant->getTenantKey(),
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)),
            );
        }

        return response()->json(['data' => $this->summarize($tenant->fresh('domains'))]);
    }

    /**
     * POST /api/admin/tenants/{tenant}/plan — upgrade/downgrade/change plan.
     *
     * Reuses SubscriptionService, which blocks a change when current usage
     * exceeds the target plan's limits unless `force` is sent (with a reason).
     */
    public function changePlan(Request $request, Tenant $tenant, SubscriptionService $subscriptions): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('central.plans', 'id')->where('is_active', true)],
            'force' => ['sometimes', 'boolean'],
            'reason' => ['required_if:force,true', 'nullable', 'string', 'max:500'],
        ]);
        $force = (bool) ($data['force'] ?? false);
        $reason = $data['reason'] ?? null;
        $target = Plan::query()->findOrFail($data['plan_id']);

        $current = Subscription::query()->with('plan')->where('tenant_id', $tenant->getTenantKey())->latest('id')->first();
        if ($current?->plan_id === $target->getKey()) {
            return response()->json(['error' => [
                'code' => 'PLAN_UNCHANGED',
                'message' => 'The tenant is already on this plan.',
            ]], 422);
        }

        $from = $current?->plan;
        if ($current === null) {
            Subscription::create([
                'tenant_id' => $tenant->getTenantKey(),
                'plan_id' => $target->getKey(),
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
            ]);
        } else {
            $subscriptions->changePlan($tenant, $target, $force, $request->user(), $reason);
        }

        $direction = $from === null ? 'assigned'
            : ((float) $target->price > (float) $from->price ? 'upgrade'
                : ((float) $target->price < (float) $from->price ? 'downgrade' : 'change'));

        PlatformAuditLog::record(
            'subscription.plan_changed',
            $request,
            (string) $tenant->getTenantKey(),
            ['plan_id' => $from?->getKey(), 'plan_name' => $from?->name, 'price' => $from?->price],
            ['plan_id' => $target->getKey(), 'plan_name' => $target->name, 'price' => $target->price,
                'direction' => $direction, 'forced' => $force],
            $reason,
        );

        return response()->json(['data' => $this->summarize($tenant->fresh('domains'), withUsage: true)]);
    }

    /** GET /api/admin/tenants/{tenant}/history — subscription change history. */
    public function history(Request $request, Tenant $tenant): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        return response()->json(
            PlatformAuditLog::query()
                ->where('tenant_id', (string) $tenant->getTenantKey())
                ->orderByDesc('id')
                ->paginate($perPage),
        );
    }

    /** GET /api/admin/usage — tenant-wise resource consumption (paginated). */
    public function usage(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $tenants = Tenant::query()->orderBy('subdomain')->paginate($perPage);

        $tenants->setCollection($tenants->getCollection()->map(function (Tenant $t): array {
            $sub = Subscription::query()->with('plan:id,name')->where('tenant_id', $t->getTenantKey())->latest('id')->first();

            return [
                'id' => $t->getTenantKey(),
                'subdomain' => $t->subdomain,
                'plan' => $sub?->plan?->name,
                'status' => $sub?->status,
                'usage' => $this->usageFor($t),
            ];
        }));

        return response()->json($tenants);
    }

    /** DELETE /api/admin/tenants/{tenant} — remove tenant + drop its database. */
    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        PlatformAuditLog::record('tenant.deleted', $request, (string) $tenant->getTenantKey(), [
            'subdomain' => $tenant->subdomain,
            'domains' => $tenant->domains()->pluck('domain')->all(),
        ]);

        // Deleting the Tenant triggers the TenantDeleted pipeline (DeleteDatabase).
        $tenant->domains()->delete();
        $tenant->subscriptions()->delete();
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted.']);
    }

    /**
     * Resource counts vs. the tenant's current plan limits. Null when the
     * tenant database cannot be reached (the list must still render).
     *
     * @return array<string, array{count: int, limit: int|null, unlimited: bool, percent: int|null}>|null
     */
    private function usageFor(Tenant $tenant): ?array
    {
        try {
            $quotas = $tenant->run(function (): array {
                $quota = app(QuotaService::class);
                $quota->invalidateAll(); // fresh numbers, not the 5-minute cache

                return $quota->usage()['quotas'];
            });
            $limit = Subscription::query()->with('plan.limits')->where('tenant_id', $tenant->getTenantKey())
                ->latest('id')->first()?->plan?->limitValue(PlanLimit::MAX_CUSTOM_DOMAINS);
            $quotas[PlanLimit::MAX_CUSTOM_DOMAINS] = [
                'count' => (int) DB::connection('central')->table('domains')
                    ->where('tenant_id', $tenant->getTenantKey())->where('type', 'custom')->count(),
                'limit' => PlanLimit::isUnlimitedValue($limit) ? null : $limit,
                'unlimited' => PlanLimit::isUnlimitedValue($limit),
            ];
            foreach ($quotas as $key => $q) {
                $quotas[$key]['percent'] = $q['unlimited'] || ! $q['limit']
                    ? null
                    : (int) min(100, round($q['count'] / $q['limit'] * 100));
            }

            return $quotas;
        } catch (Throwable $e) {
            Log::warning('tenant usage unavailable', ['tenant_id' => $tenant->getTenantKey(), 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function editableState(Tenant $tenant, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $tenant->getAttribute($key);
        }

        return $out;
    }

    /**
     * Flat central summary for a tenant: identity, details, domains and the
     * latest subscription (plan + status), optionally with live usage.
     *
     * @return array<string, mixed>
     */
    private function summarize(Tenant $tenant, bool $withUsage = false): array
    {
        $subscription = $tenant->subscriptions()->with('plan.limits')->latest('id')->first();
        $plan = $subscription?->plan;

        $out = [
            'id' => $tenant->getTenantKey(),
            'subdomain' => $tenant->subdomain,
            'currency' => $tenant->currency,
            'backorders_enabled' => (bool) $tenant->backorders_enabled,
            'dealer_stock_enabled' => (bool) $tenant->dealer_stock_enabled,
            'created_at' => $tenant->created_at,
            'domains' => $tenant->domains->pluck('domain')->all(),
            'details' => collect(self::DETAIL_FIELDS)
                ->mapWithKeys(fn (string $f) => [$f => $tenant->getAttribute($f)])->all(),
            'subscription' => $subscription ? [
                'id' => $subscription->getKey(),
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'plan' => $plan ? [
                    'id' => $plan->getKey(),
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'billing_cycle' => $plan->billing_cycle,
                    'limits' => $plan->limits->map(fn ($l) => ['key' => $l->key, 'value' => $l->value])->values()->all(),
                ] : null,
            ] : null,
        ];
        if ($withUsage) {
            $out['usage'] = $this->usageFor($tenant);
        }

        return $out;
    }
}
