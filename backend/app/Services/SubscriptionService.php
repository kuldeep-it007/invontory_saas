<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DowngradeBlockedException;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plan assignment, upgrade, and downgrade with usage protection (R6).
 *
 * A Platform_Admin changes a tenant's plan from the `platform.admin` route
 * group, which runs against the CENTRAL database WITHOUT tenancy initialized.
 * The {@see Subscription}, {@see Plan}, and {@see PlanLimit} rows all live in
 * the central database, so the subscription mutation happens centrally. The
 * usage check, however, must count the tenant's OWN resources, which live in
 * the per-tenant database — so this service EXPLICITLY initializes the target
 * tenant's database (via {@see Tenant::run()} + {@see QuotaService}) just to
 * read the counts, exactly like impersonation initializes a tenant for a
 * sanctioned cross-tenant read.
 *
 * ## Behaviour (R6.1, R6.2, R6.3)
 *
 *   • Any plan change (assign / upgrade / downgrade) updates the tenant's
 *     subscription to the new plan and records the change centrally (R6.1).
 *   • A downgrade is BLOCKED when the target plan would set a limit BELOW the
 *     tenant's current usage of that resource; the error names every violating
 *     resource (R6.2). "Below" means a finite target limit strictly less than
 *     the current count — moving to UNLIMITED (0/NULL) never violates (R4.4).
 *   • A Platform_Admin may explicitly FORCE a violating downgrade; it is then
 *     applied and the forced override (with its reason) is recorded (R6.3).
 *
 * ## Central-side audit (R6.1, R6.3, R34.3)
 *
 * Like {@see TenantProvisioningService}, the structured `audit_logs` table is a
 * TENANT-side table and this action runs centrally, so the coherent central
 * audit sink is Laravel's application logger. Every plan change writes a
 * `subscription.plan_changed` entry (actor, tenant, from/to plan, forced flag,
 * reason, any violations) — satisfying "plan change ... recorded with reason"
 * (R34.3). A forced override is clearly flagged in the same entry (R6.3).
 */
class SubscriptionService
{
    /**
     * Central-side audit event name for a plan change (R6.1, R34.3).
     */
    public const AUDIT_EVENT = 'subscription.plan_changed';

    public function __construct(private readonly QuotaService $quota) {}

    /**
     * Assign, upgrade, or downgrade a tenant's plan (R6).
     *
     * @param  Tenant  $tenant  The target tenant (central registry row).
     * @param  Plan  $targetPlan  The plan to move the subscription to.
     * @param  bool  $force  When true, apply even a usage-violating downgrade (R6.3).
     * @param  User|null  $actor  The Platform_Admin performing the change (central user).
     * @param  string|null  $reason  Reason recorded with the audit entry; required in spirit for a forced override (R6.3).
     * @return Subscription The updated subscription (with plan loaded).
     *
     * @throws DowngradeBlockedException When the downgrade violates current usage and is not forced (R6.2).
     */
    public function changePlan(
        Tenant $tenant,
        Plan $targetPlan,
        bool $force = false,
        ?User $actor = null,
        ?string $reason = null,
    ): Subscription {
        $subscription = $this->resolveSubscription($tenant);
        $fromPlan = $subscription->plan;

        // Detect any limits the target plan would set below current usage (R6.2).
        $violations = $this->downgradeViolations($tenant, $targetPlan);

        if ($violations !== [] && ! $force) {
            // Blocked downgrade: name every violating resource (R6.2). The
            // subscription is left untouched.
            throw new DowngradeBlockedException($violations);
        }

        // Apply the change centrally in a single transaction (R6.1).
        $updated = DB::connection('central')->transaction(function () use ($subscription, $targetPlan): Subscription {
            $subscription->plan_id = $targetPlan->getKey();
            $subscription->save();

            return $subscription->fresh('plan') ?? $subscription;
        });

        // Record the change centrally, flagging a forced override + reason
        // (R6.1, R6.3, R34.3).
        $this->auditPlanChange($tenant, $fromPlan, $targetPlan, $force, $violations, $actor, $reason);

        return $updated;
    }

    /**
     * The quota keys whose target limit, if lower than current usage, blocks a
     * downgrade. These are the whole-tenant counted resources (R6.2).
     *
     * @return array<string, array{count: int, target_limit: int}>
     *                                                             Map of quota key => current count + the violating lower target limit.
     */
    private function downgradeViolations(Tenant $tenant, Plan $targetPlan): array
    {
        $checkedKeys = [
            PlanLimit::MAX_DISTRIBUTORS,
            PlanLimit::MAX_DEALERS,
            PlanLimit::MAX_PRODUCTS,
            PlanLimit::MAX_STOCK_POINTS,
            PlanLimit::MAX_USERS,
        ];

        // Read current usage INSIDE the target tenant's database. Counts live
        // in the per-tenant DB; the target plan + its limits are central.
        $counts = $tenant->run(function () use ($checkedKeys): array {
            $counts = [];
            foreach ($checkedKeys as $key) {
                $counts[$key] = $this->quota->count($key);
            }

            return $counts;
        });

        $violations = [];

        foreach ($checkedKeys as $key) {
            $targetValue = $targetPlan->limitValue($key);

            // Moving to UNLIMITED (0/NULL) can never violate current usage (R4.4).
            if (PlanLimit::isUnlimitedValue($targetValue)) {
                continue;
            }

            $count = $counts[$key] ?? 0;

            // A violation iff the finite target limit is strictly below usage.
            if ($targetValue < $count) {
                $violations[$key] = [
                    'count' => $count,
                    'target_limit' => (int) $targetValue,
                ];
            }
        }

        return $violations;
    }

    /**
     * Resolve the tenant's current subscription from the central database.
     *
     * @throws ModelNotFoundException When the tenant has no subscription.
     */
    private function resolveSubscription(Tenant $tenant): Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('tenant_id', $tenant->getTenantKey())
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * Record the plan change centrally (R6.1, R6.3, R34.3). Best-effort: a
     * failed audit write must never undo an applied plan change.
     *
     * @param  array<string, array{count: int, target_limit: int}>  $violations
     */
    private function auditPlanChange(
        Tenant $tenant,
        ?Plan $fromPlan,
        Plan $targetPlan,
        bool $force,
        array $violations,
        ?User $actor,
        ?string $reason,
    ): void {
        try {
            Log::channel(config('logging.default'))->info(self::AUDIT_EVENT, [
                'event' => self::AUDIT_EVENT,
                'tenant_id' => $tenant->getTenantKey(),
                'from_plan_id' => $fromPlan?->getKey(),
                'from_plan_name' => $fromPlan?->name,
                'to_plan_id' => $targetPlan->getKey(),
                'to_plan_name' => $targetPlan->name,
                'forced' => $force,
                'had_usage_violation' => $violations !== [],
                'violations' => $violations,
                'reason' => $reason,
                'actor_id' => $actor?->getKey(),
                'actor_email' => $actor?->email,
                'changed_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            // Best-effort: never let a central-audit failure undo the change.
        }
    }
}
