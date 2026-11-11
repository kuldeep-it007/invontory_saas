<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Services\TenantProvisioningService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Company model (singleton).
 *
 * The single top-level organization that owns master inventory. Each tenant
 * database contains EXACTLY ONE company row (R1.5, R1.6). Because isolation is
 * physical (one database per tenant) there is no `tenant_id` column; the
 * singleton rule is enforced in the application layer (see
 * {@see TenantProvisioningService}) rather than by a DB
 * constraint.
 *
 * This is a tenant-owned model, so {@see TenantAuditable} records its
 * create/update/delete in the tenant `audit_logs` table (R34.1); the active
 * tenant connection resolves it to the current tenant's database.
 */
class Company extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * Mass-assignable attributes. The company is created once during tenant
     * provisioning with its display name and (optional) GSTIN.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'gstin',
    ];

    /**
     * The stock points this Company owns (R13.4). A Company may own one or more
     * stock points; the relation is polymorphic via the `owner` morph so a
     * Distributor (and, when enabled, a Dealer) can own stock points the same
     * way (R13.5, R13.7).
     *
     * @return MorphMany<StockPoint, $this>
     */
    public function stockPoints(): MorphMany
    {
        return $this->morphMany(StockPoint::class, 'owner');
    }
}
