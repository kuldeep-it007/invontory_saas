<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Central-database Tenant model (stancl/tenancy).
 *
 * One row per tenant in the CENTRAL database. The tenant is resolved from the
 * request's subdomain / custom domain (via the `domains` table) and, once
 * tenancy is initialized, the DatabaseTenancyBootstrapper switches Laravel's
 * default connection to this tenant's own database (R1.1, R1.2).
 *
 * Isolation is physical: all tenant-owned business data lives in a dedicated
 * per-tenant database, so there is no `tenant_id` discriminator column, no
 * global Eloquent scope, and no `BelongsToTenant` trait anywhere in the app.
 *
 * This model implements {@see TenantWithDatabase} (so stancl/tenancy can
 * create/migrate the per-tenant database) and uses {@see HasDomains} so the
 * subdomain / custom-domain resolution relationship is available.
 *
 * The custom business columns (`subdomain`, `currency`, `backorders_enabled`,
 * `dealer_stock_enabled`) are declared in {@see getCustomColumns()} so the
 * VirtualColumn trait treats them as real table columns instead of folding
 * them into the JSON `data` column.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * Attribute casting for the custom (real) columns.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'backorders_enabled' => 'boolean',
        'dealer_stock_enabled' => 'boolean',
    ];

    /**
     * Columns that physically exist on the `tenants` table.
     *
     * Anything NOT listed here is serialized into the JSON `data` column by the
     * VirtualColumn trait. These are the real columns created by the central
     * `tenants` migration, so they are queryable and resolvable directly.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'subdomain',
            'currency',
            'backorders_enabled',
            'dealer_stock_enabled',
        ];
    }

    /**
     * Central subscriptions for this tenant (R4.6). Logical reference by
     * `tenant_id` in the central database (not a tenant-owned column).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Subscription, $this>
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }
}
