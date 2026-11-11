<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Dealer model.
 *
 * An organization that buys from a Distributor and (by default) consumes stock
 * without holding any (R10.2, R10.3, R13.6). A Dealer belongs to exactly one
 * Distributor. Because isolation is physical (one database per tenant) there is
 * no `tenant_id` column.
 *
 * This is a tenant-owned model, so {@see TenantAuditable} records its
 * create/update/delete — including Payment_Terms updates (R10.5) — in the
 * tenant `audit_logs` table (R34.1).
 */
class Dealer extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'distributor_id',
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
     * Payment_Terms stored as JSON (R10.2, R26.1). `profile` holds the same
     * Rosetta-style party detail block used for Distributors (territory,
     * billing location, shipment method, shipping advice, reserve, GST + PAN
     * details) so Dealers carry the identical profile (R10.2).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'profile' => 'array',
        'payment_terms' => 'array',
    ];

    /**
     * The single Distributor this Dealer belongs to (R10.3).
     *
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * The active-product-attribute grants configured for this Dealer as a
     * Buyer (R11.4). When empty, the Dealer sees the whole catalog (R11.6);
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
