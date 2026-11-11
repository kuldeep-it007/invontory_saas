<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Services\ProspectService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Prospect model (R9.1–R9.4).
 *
 * A prospective buyer's self sign-up, held with contact details and a
 * lifecycle status until a Company_Admin approves or rejects it. Approval
 * creates the corresponding Distributor or Dealer and sends an account-created
 * email (R9.2, R9.4); rejection marks the row `rejected` (R9.3). Because
 * isolation is physical (one database per tenant) there is no `tenant_id`
 * column.
 *
 * This is a tenant-owned model, so {@see TenantAuditable} records its
 * create/update in the tenant `audit_logs` table (R34.1); approval and
 * rejection are additionally recorded as explicit audit events by
 * {@see ProspectService} (R9.2, R9.3).
 */
class Prospect extends Model implements Auditable
{
    use TenantAuditable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_DISTRIBUTOR = 'distributor';

    public const TYPE_DEALER = 'dealer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'gstin',
        'address',
        'intended_buyer_type',
        'status',
        'serving_distributor_id',
    ];

    /**
     * The Distributor a dealer prospect wishes to be served by (R20.2); null for
     * distributor prospects.
     *
     * @return BelongsTo<Distributor, $this>
     */
    public function servingDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'serving_distributor_id');
    }

    /**
     * True while the prospect is still awaiting a decision (R9.1).
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * True when the prospect wants to become a Dealer (R9.2).
     */
    public function isDealer(): bool
    {
        return $this->intended_buyer_type === self::TYPE_DEALER;
    }
}
