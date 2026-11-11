<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Distributor model.
 *
 * An organization that buys stock from the single tenant Company and sells on
 * to Dealers (R10.1, R10.3). A Distributor belongs to exactly one Company and
 * owns many Dealers. Because isolation is physical (one database per tenant)
 * there is no `tenant_id` column.
 *
 * This is a tenant-owned model, so {@see TenantAuditable} records its
 * create/update/delete — including Payment_Terms updates (R10.5) — in the
 * tenant `audit_logs` table (R34.1).
 */
class Distributor extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'fax',
        'gstin',
        'address',
        'profile',
        'payment_terms',
    ];

    /**
     * Payment_Terms stored as JSON (R10.1, R26.1): credit_limit,
     * overdue_balance_limit, overdue_hold_days, payment_type. `profile` holds
     * the Rosetta-style party detail block (territory, billing location,
     * shipment method, shipping advice, reserve, GST + PAN details) — R10.1.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'profile' => 'array',
        'payment_terms' => 'array',
    ];

    /**
     * The single Company this Distributor belongs to (R10.3).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The Dealers owned by this Distributor (R10.3).
     *
     * @return HasMany<Dealer, $this>
     */
    public function dealers(): HasMany
    {
        return $this->hasMany(Dealer::class);
    }

    /**
     * The active-product-attribute grants configured for this Distributor as a
     * Buyer (R11.4). When empty, the Distributor sees the whole catalog (R11.6);
     * when non-empty, the catalog is restricted to the referenced attributes
     * (R11.4, R11.5).
     *
     * @return MorphMany<ActiveProductAttribute, $this>
     */
    public function activeProductAttributes(): MorphMany
    {
        return $this->morphMany(ActiveProductAttribute::class, 'buyer');
    }
}
