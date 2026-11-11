<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cached per-tenant quota accounting (R5.1, R5.2, R5.3, R5.6).
 *
 * `QuotaService` is the single authority for "how many of resource X does this
 * tenant currently have?" and "what is the active plan limit for resource X?".
 * It composes the two sides of a quota from the two databases the platform
 * uses (see design "Quota Caching Model"):
 *
 *   • COUNTS come from the ACTIVE TENANT database — the number of Distributors,
 *     Dealers, Products, Stock_Points, Users (and monthly Orders) owned by the
 *     current tenant. Counts are cached per tenant and invalidated when a
 *     counted resource is created or deleted (R5.6).
 *   • LIMITS come from the CENTRAL database — the active {@see Subscription}'s
 *     {@see Plan} and its {@see PlanLimit} rows. A limit of 0 or NULL (no row)
 *     means UNLIMITED (R4.4).
 *
 * A quota is enforced by comparing count vs limit BEFORE creation: creation is
 * permitted iff the limit is unlimited or the current count is strictly less
 * than the limit; otherwise a {@see QuotaExceededException}
 * (HTTP 403, naming the quota) is thrown (R5.1, R5.2).
 *
 * ## Tables that may not exist yet
 *
 * The counted resource tables (distributors, dealers, products, stock_points)
 * are introduced in later milestones (M3). This service only counts a resource
 * when its table physically exists in the active tenant database, so quota
 * accounting is correct in every milestone and a not-yet-created table simply
 * contributes a count of 0. The `users` table always exists.
 */
class QuotaService
{
    /**
     * Map of quota key => the tenant-database table whose row count feeds that
     * quota (R4.3, R5.1). `max_orders_per_month` is handled specially (counted
     * for the current calendar month) and `max_dealers_per_distributor` /
     * `max_storage_mb` are not simple whole-table counts, so they are omitted
     * from this straightforward table map.
     *
     * @var array<string, string>
     */
    private const COUNTED_TABLES = [
        PlanLimit::MAX_DISTRIBUTORS => 'distributors',
        PlanLimit::MAX_DEALERS => 'dealers',
        PlanLimit::MAX_PRODUCTS => 'products',
        PlanLimit::MAX_STOCK_POINTS => 'stock_points',
        PlanLimit::MAX_USERS => 'users',
    ];

    /**
     * Cache TTL for a per-tenant count. Counts are also eagerly invalidated on
     * create/delete (R5.6); the TTL is a safety net so a stale count can never
     * live indefinitely.
     */
    private const COUNT_TTL_SECONDS = 300;

    /**
     * The current count for a counted resource in the active tenant database,
     * served from the per-tenant cache (R5.6). Returns 0 when the resource's
     * table does not exist yet in this milestone.
     */
    public function count(string $quotaKey): int
    {
        return (int) Cache::remember(
            $this->countCacheKey($quotaKey),
            self::COUNT_TTL_SECONDS,
            fn (): int => $this->computeCount($quotaKey),
        );
    }

    /**
     * The active plan limit for a quota key, read from the central database via
     * the active {@see Subscription}'s {@see Plan}. Returns null for UNLIMITED
     * (no plan, no row, or a stored value of 0/NULL — R4.4).
     */
    public function limit(string $quotaKey): ?int
    {
        $plan = $this->activePlan();

        if ($plan === null) {
            return null;
        }

        $value = $plan->limitValue($quotaKey);

        return PlanLimit::isUnlimitedValue($value) ? null : $value;
    }

    /**
     * Whether a quota key is unlimited for the active plan (R4.4).
     */
    public function isUnlimited(string $quotaKey): bool
    {
        return $this->limit($quotaKey) === null;
    }

    /**
     * Whether creating one more of a resource is permitted under the active
     * plan: true iff unlimited OR current count is strictly less than the limit
     * (R5.1, R5.2).
     */
    public function allows(string $quotaKey): bool
    {
        $limit = $this->limit($quotaKey);

        if ($limit === null) {
            return true;
        }

        return $this->count($quotaKey) < $limit;
    }

    /**
     * Enforce a quota BEFORE creating a resource (R5.1, R5.2, R5.3, R10.4).
     *
     * This is the single throwing entry point the request pipeline calls just
     * before persisting a counted resource. It compares the active count
     * against the active limit and, when the quota is NOT unlimited and the
     * count has reached/exceeded the limit, throws a {@see QuotaExceededException}
     * — an HTTP 403 whose message NAMES the exceeded quota (R5.2). When the
     * quota is unlimited or the count is strictly below the limit, it returns
     * quietly and creation may proceed.
     *
     * @throws QuotaExceededException when the quota is reached (HTTP 403, named).
     */
    public function assertAllows(string $quotaKey): void
    {
        $limit = $this->limit($quotaKey);

        if ($limit === null) {
            // Unlimited (0/NULL — R4.4): creation always permitted.
            return;
        }

        $count = $this->count($quotaKey);

        if ($count < $limit) {
            // Strictly below the limit: creation permitted (R5.1).
            return;
        }

        // Count has met or exceeded the limit: reject with 403 naming the
        // quota (R5.2).
        throw new QuotaExceededException($quotaKey, $count, $limit);
    }

    /**
     * Enforce the per-Distributor dealer cap BEFORE creating a Dealer (R10.4).
     *
     * Unlike the whole-tenant quotas, `max_dealers_per_distributor` is scoped
     * to a single Distributor, so it is NOT a simple whole-table count and is
     * handled here rather than in {@see assertAllows()}. The cap applies ONLY
     * WHERE the plan sets it to a positive value (R10.4): a value of 0/NULL (no
     * row) is UNLIMITED (R4.4) and the check returns quietly.
     *
     * When the Distributor already has as many Dealers as the cap allows, a
     * {@see QuotaExceededException} (HTTP 403, naming `max_dealers_per_distributor`)
     * is thrown; otherwise creation may proceed (R10.4).
     *
     * @param  int  $distributorId  The Distributor whose dealers are being counted.
     *
     * @throws QuotaExceededException when the per-distributor cap is reached (HTTP 403, named).
     */
    public function assertAllowsDealerForDistributor(int $distributorId): void
    {
        $limit = $this->limit(PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR);

        if ($limit === null) {
            // Unlimited (0/NULL — R4.4) or not configured: always permitted.
            return;
        }

        $connection = $this->tenantConnection();

        if (! Schema::connection($connection)->hasTable('dealers')) {
            return;
        }

        $count = (int) DB::connection($connection)
            ->table('dealers')
            ->where('distributor_id', $distributorId)
            ->count();

        if ($count < $limit) {
            // Strictly below the per-distributor cap: creation permitted (R10.4).
            return;
        }

        throw new QuotaExceededException(PlanLimit::MAX_DEALERS_PER_DISTRIBUTOR, $count, $limit);
    }

    /**
     * Invalidate the cached count for a quota key so the next read recomputes
     * from the tenant database (R5.6). Call after creating or deleting a
     * counted resource.
     */
    public function invalidate(string $quotaKey): void
    {
        Cache::forget($this->countCacheKey($quotaKey));
    }

    /**
     * Invalidate every cached count for the active tenant (R5.6).
     */
    public function invalidateAll(): void
    {
        foreach (array_keys(self::COUNTED_TABLES) as $quotaKey) {
            $this->invalidate($quotaKey);
        }

        $this->invalidate(PlanLimit::MAX_ORDERS_PER_MONTH);
    }

    /**
     * A coherent usage-vs-limits snapshot for the active tenant (R5.5): for
     * every supported quota key, the current count, the active limit, whether
     * it is unlimited, and the remaining headroom. Backs the `GET /usage`
     * endpoint and the dashboard widget.
     *
     * @return array{
     *     plan: array{id: int|string|null, name: string|null}|null,
     *     subscription_status: string|null,
     *     quotas: array<string, array{count: int, limit: int|null, unlimited: bool, remaining: int|null}>
     * }
     */
    public function usage(): array
    {
        $subscription = $this->activeSubscription();
        $plan = $subscription?->plan;

        $quotas = [];

        foreach ($this->usageKeys() as $quotaKey) {
            $count = $this->count($quotaKey);
            $limit = $this->limit($quotaKey);

            $quotas[$quotaKey] = [
                'count' => $count,
                'limit' => $limit,
                'unlimited' => $limit === null,
                'remaining' => $limit === null ? null : max(0, $limit - $count),
            ];
        }

        return [
            'plan' => $plan === null ? null : [
                'id' => $plan->getKey(),
                'name' => $plan->name,
            ],
            'subscription_status' => $subscription?->status,
            'quotas' => $quotas,
        ];
    }

    /**
     * The quota keys surfaced by the usage snapshot: the countable resources
     * plus monthly orders. (Non-countable keys like per-distributor dealer
     * caps and storage are enforced elsewhere and not whole-tenant counts.)
     *
     * @return list<string>
     */
    private function usageKeys(): array
    {
        return array_merge(
            array_keys(self::COUNTED_TABLES),
            [PlanLimit::MAX_ORDERS_PER_MONTH],
        );
    }

    /**
     * Compute the live count for a quota key from the active tenant database.
     * Returns 0 when the backing table does not yet exist (R: milestone-safe).
     */
    private function computeCount(string $quotaKey): int
    {
        if ($quotaKey === PlanLimit::MAX_ORDERS_PER_MONTH) {
            return $this->computeMonthlyOrderCount();
        }

        $table = self::COUNTED_TABLES[$quotaKey] ?? null;

        if ($table === null || ! Schema::connection($this->tenantConnection())->hasTable($table)) {
            return 0;
        }

        return (int) DB::connection($this->tenantConnection())->table($table)->count();
    }

    /**
     * Count Orders created in the current calendar month for the active tenant
     * (R5.3). Returns 0 when the `orders` table does not exist yet.
     */
    private function computeMonthlyOrderCount(): int
    {
        $connection = $this->tenantConnection();

        if (! Schema::connection($connection)->hasTable('orders')) {
            return 0;
        }

        return (int) DB::connection($connection)
            ->table('orders')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /**
     * The active plan for the current tenant, read from the central database
     * (null when there is no active subscription or plan).
     */
    private function activePlan(): ?Plan
    {
        return $this->activeSubscription()?->plan;
    }

    /**
     * The current tenant's subscription row from the CENTRAL database, with its
     * plan + limits eager-loaded. Resolves the tenant from the active tenancy
     * context; returns null when no tenancy is initialized or no subscription
     * exists.
     */
    private function activeSubscription(): ?Subscription
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId === null) {
            return null;
        }

        return Subscription::query()
            ->with('plan.limits')
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
    }

    /**
     * The active tenant id from the tenancy context, or null when no tenant is
     * initialized (e.g. central/platform routes).
     */
    private function currentTenantId(): int|string|null
    {
        if (! function_exists('tenant')) {
            return null;
        }

        /** @var int|string|null $id */
        $id = tenant('id');

        return $id;
    }

    /**
     * The Laravel connection name for the active tenant database.
     */
    private function tenantConnection(): string
    {
        return 'tenant';
    }

    /**
     * Per-tenant, per-quota cache key so counts never collide across tenants
     * (the cache is additionally tenant-tagged by stancl/tenancy).
     */
    private function countCacheKey(string $quotaKey): string
    {
        return sprintf('quota:%s:count:%s', (string) ($this->currentTenantId() ?? 'none'), $quotaKey);
    }
}
