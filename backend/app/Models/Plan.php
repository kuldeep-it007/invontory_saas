<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central-database Plan model.
 *
 * A subscription tier defining price, billing cycle, and active state (R4.1);
 * quota values live in the related `plan_limits` table (R4.2). Plans are
 * cross-tenant platform configuration, so this model is pinned to the central
 * connection — it must resolve against the central database even while a
 * tenant connection is the default (e.g. inside tenancy context).
 */
class Plan extends Model
{
    /**
     * Pin to the central connection so plan reads/writes never follow the
     * active tenant default connection.
     */
    protected $connection = 'central';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'billing_cycle',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The flexible key/value usage quotas attached to this plan (R4.2).
     *
     * @return HasMany<PlanLimit, $this>
     */
    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    /**
     * The per-plan feature flags attached to this plan (R4.5).
     *
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * The subscriptions assigned to this plan (R4.6).
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Resolve the configured value for a quota key, or NULL when the plan has
     * no row for that key (treated as unlimited by R4.4 downstream).
     */
    public function limitValue(string $key): ?int
    {
        /** @var PlanLimit|null $limit */
        $limit = $this->limits()->where('key', $key)->first();

        return $limit?->value;
    }

    /**
     * Whether a quota key is UNLIMITED for this plan (R4.4): either no row
     * exists for the key, or the stored value is 0/NULL.
     */
    public function isUnlimited(string $key): bool
    {
        return PlanLimit::isUnlimitedValue($this->limitValue($key));
    }

    /**
     * Whether a feature flag is enabled on this plan (R4.5). A flag with no row
     * is treated as disabled (optional modules are off by default).
     */
    public function featureEnabled(string $key): bool
    {
        return (bool) $this->features()
            ->where('key', $key)
            ->where('enabled', true)
            ->exists();
    }
}
