<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FeatureDisabledException;
use App\Exceptions\SubscriptionInactiveException;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;

/**
 * Focused service for the subscription-status gating and feature-flag guard
 * (R5.4, R5.7) — the "Subscription + quota guard" step of the tenant request
 * pipeline (design: "Request Pipeline / Middleware Order", step 5).
 *
 * Both checks read the plan/limits/subscription from the CENTRAL database
 * (where {@see Subscription}, {@see Plan}, and {@see \App\Models\PlanFeature}
 * are pinned) while the request operates against the active tenant database.
 * The active tenant is resolved from `tenant('id')`, set by stancl/tenancy's
 * identification middleware earlier in the pipeline.
 *
 * This service is intentionally narrow: it answers two questions and throws the
 * matching 403 on denial. Quota count-vs-limit enforcement (R5.1–R5.3) lives in
 * the separate `QuotaService` (task 5.3); this service owns only the
 * subscription-status gate (R5.7) and the feature-flag gate (R5.4).
 */
class SubscriptionGateService
{
    /**
     * Subscription statuses that BLOCK creation of new tenant-owned resources
     * (R5.7). `active` and `trial` are permitted to create; `past_due` and
     * `cancelled` are not.
     *
     * @var list<string>
     */
    public const BLOCKING_STATUSES = [
        Subscription::STATUS_PAST_DUE,
        Subscription::STATUS_CANCELLED,
    ];

    /**
     * Assert the active tenant may create a new tenant-owned resource (R5.7).
     *
     * Creation is denied with a 403 when the tenant's active subscription
     * status is `past_due` or `cancelled`. `active` and `trial` pass. A tenant
     * with no subscription row on record is treated as NOT blocked here — the
     * absence of a central subscription is a provisioning concern, not a
     * past-due/cancelled state, so this gate stays scoped strictly to R5.7.
     *
     * @param  string|int|null  $tenantId  The active tenant id; defaults to `tenant('id')`.
     *
     * @throws SubscriptionInactiveException When the subscription is past_due/cancelled.
     */
    public function assertResourceCreationAllowed(string|int|null $tenantId = null): void
    {
        $subscription = $this->activeSubscription($tenantId);

        if ($subscription === null) {
            return;
        }

        if (in_array($subscription->status, self::BLOCKING_STATUSES, true)) {
            throw new SubscriptionInactiveException($subscription->status);
        }
    }

    /**
     * Whether creation of new tenant-owned resources is currently permitted for
     * the active tenant (R5.7). Non-throwing companion to
     * {@see assertResourceCreationAllowed()}.
     */
    public function resourceCreationAllowed(string|int|null $tenantId = null): bool
    {
        $subscription = $this->activeSubscription($tenantId);

        if ($subscription === null) {
            return true;
        }

        return ! in_array($subscription->status, self::BLOCKING_STATUSES, true);
    }

    /**
     * Assert the given optional-module feature flag is enabled on the active
     * tenant's plan (R5.4); otherwise deny with a 403.
     *
     * @param  string|int|null  $tenantId  The active tenant id; defaults to `tenant('id')`.
     *
     * @throws FeatureDisabledException When the feature flag is disabled (or no plan resolves).
     */
    public function assertFeatureEnabled(string $feature, string|int|null $tenantId = null): void
    {
        if (! $this->featureEnabled($feature, $tenantId)) {
            throw new FeatureDisabledException($feature);
        }
    }

    /**
     * Whether an optional-module feature flag is enabled on the active tenant's
     * plan (R5.4). A flag with no row — or a tenant with no resolvable
     * plan/subscription — is treated as DISABLED, because optional modules are
     * off by default (design: Auth & RBAC / feature flags).
     */
    public function featureEnabled(string $feature, string|int|null $tenantId = null): bool
    {
        $plan = $this->activePlan($tenantId);

        if ($plan === null) {
            return false;
        }

        return $plan->featureEnabled($feature);
    }

    /**
     * Whether AI reporting is permitted for the active tenant (R30.6, R30.7).
     *
     * The AI layer carries a COMPOSITE gate: it is permitted if and only if
     * BOTH the platform-wide `AI_REPORTS_ENABLED` env master switch is on
     * (R30.6, default off per R30.1) AND the tenant's own `ai_reports` feature
     * flag is enabled on its plan (R30.7). Either one off denies the module.
     *
     * This is the logical conjunction the design specifies for Property 19 and
     * the entry in the error map ("AI disabled (master switch or tenant flag) →
     * 403"). The master switch is read from config (`services.ai.reports_enabled`,
     * populated from the env var) so it is cacheable; the tenant flag is read
     * from the central plan via {@see featureEnabled()}.
     */
    public function aiReportingEnabled(string|int|null $tenantId = null): bool
    {
        return $this->aiMasterSwitchEnabled()
            && $this->featureEnabled(PlanFeature::AI_REPORTS, $tenantId);
    }

    /**
     * Assert AI reporting is permitted for the active tenant (R30.6, R30.7);
     * otherwise deny with a 403.
     *
     * @throws FeatureDisabledException When the master switch OR the tenant
     *                                  `ai_reports` flag is off.
     */
    public function assertAiReportingEnabled(string|int|null $tenantId = null): void
    {
        if (! $this->aiReportingEnabled($tenantId)) {
            throw new FeatureDisabledException(PlanFeature::AI_REPORTS);
        }
    }

    /**
     * Whether the platform-wide AI master switch is enabled (R30.6). Reads the
     * `AI_REPORTS_ENABLED` env var via config so it defaults to OFF (R30.1) and
     * requires no AI configuration to boot (R30.3).
     */
    public function aiMasterSwitchEnabled(): bool
    {
        return (bool) config('services.ai.reports_enabled', false);
    }

    /**
     * Resolve the active tenant's current subscription from the CENTRAL
     * database, or null when none exists. The latest subscription row for the
     * tenant is authoritative.
     */
    public function activeSubscription(string|int|null $tenantId = null): ?Subscription
    {
        $tenantId ??= $this->currentTenantId();

        if ($tenantId === null) {
            return null;
        }

        return Subscription::query()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
    }

    /**
     * Resolve the active tenant's plan (with limits + features eager-loaded)
     * from the CENTRAL database, or null when no subscription/plan resolves.
     */
    public function activePlan(string|int|null $tenantId = null): ?Plan
    {
        $subscription = $this->activeSubscription($tenantId);

        if ($subscription === null) {
            return null;
        }

        return Plan::query()
            ->with(['limits', 'features'])
            ->find($subscription->plan_id);
    }

    /**
     * The active tenant id resolved by stancl/tenancy, or null outside tenancy
     * context (e.g. central routes, where this gate does not apply).
     */
    private function currentTenantId(): string|int|null
    {
        if (! function_exists('tenant') || tenant() === null) {
            return null;
        }

        return tenant('id');
    }
}
