<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central-database Subscription model.
 *
 * Associates a {@see Tenant} with a {@see Plan} and a lifecycle status (R4.6,
 * R2.2). `tenant_id` here is a LOGICAL reference to the central `tenants` row,
 * NOT a tenant-owned discriminator column — subscriptions live in the central
 * database alongside the tenant registry. The model is pinned to the central
 * connection so it resolves against the central database regardless of the
 * active tenant default connection.
 */
class Subscription extends Model
{
    protected $connection = 'central';

    /**
     * Lifecycle statuses (R4.6). Also consulted by the subscription-status
     * guard that gates resource creation (R5.7).
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_TRIAL = 'trial';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
