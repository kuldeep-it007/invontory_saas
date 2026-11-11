<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Stock_Point model.
 *
 * A named location that holds Stock, owned polymorphically by a Company or a
 * Distributor — and optionally a Dealer only when dealer stock-holding is
 * enabled for the tenant (R13.4, R13.5, R13.7). An owner may own one or more
 * stock points, each with a display name and a sort order (R13.8).
 *
 * Tenant-owned and physically isolated (no `tenant_id` column); {@see
 * TenantAuditable} records create/update/delete in the tenant `audit_logs`
 * table (R34.1), resolved against the active tenant database.
 *
 * @property string $name
 * @property int $sort_order
 */
class StockPoint extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * Mass-assignable attributes. The polymorphic owner is assigned through the
     * {@see owner()} relation rather than mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * The polymorphic owner of this stock point: a {@see Company}, a
     * {@see Distributor}, or (when dealer stock-holding is enabled) a Dealer
     * (R13.4, R13.5, R13.7).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The stock rows held at this stock point (R13.3).
     *
     * @return HasMany<Stock, $this>
     */
    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
