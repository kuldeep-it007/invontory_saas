<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Category model.
 *
 * Top level of the catalog hierarchy Category → Collection → Colour (R11.2).
 * Tenant-owned: every create/update/delete is recorded in the tenant
 * `audit_logs` table via {@see TenantAuditable} (R34.1), and the active tenant
 * connection resolves it to the current tenant's database (no `tenant_id`
 * column — isolation is physical).
 */
class Category extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /** Only active categories (Rosetta-style `active` flag). */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * The collections grouped under this category (R11.2).
     *
     * @return HasMany<Collection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
