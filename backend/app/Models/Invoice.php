<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Invoice model.
 *
 * Exactly one invoice is generated per confirmed Order, synchronously inside
 * the confirmation transaction, using the SELLER's own invoice `series` with a
 * strictly-increasing per-series `number` (R21.5). Allocation and persistence
 * are owned by {@see App\Services\InvoiceService::generate()}, which serializes
 * number assignment under the confirmation transaction so numbers never
 * collide or regress across concurrent confirmations.
 *
 * The Seller is polymorphic (`seller`): the single tenant {@see Company} (for
 * distributor orders) or a {@see Distributor} (for dealer orders), mirroring
 * the Order's seller morph.
 *
 * Tenant-owned and physically isolated — there is no `tenant_id` column; the
 * row already lives in the active tenant's database. {@see TenantAuditable}
 * records create/update/delete in the tenant `audit_logs` table (R34.1).
 *
 * Amounts use DECIMAL(15,2) kept as strings via the `decimal` cast so monetary
 * values are never silently coerced to float, preserving exactness in the
 * tenant's single currency (R39.1).
 *
 * @property int $order_id
 * @property string $seller_type
 * @property int $seller_id
 * @property string $series
 * @property int $number
 * @property string $amount
 * @property bool $overdue
 */
class Invoice extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * Mass-assignable attributes. The polymorphic seller is assigned through
     * the {@see seller()} relation (or the morph columns) by the service rather
     * than through arbitrary mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'series',
        'number',
        'amount',
        'overdue',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'seller_id' => 'integer',
        'number' => 'integer',
        'amount' => 'decimal:2',
        'overdue' => 'boolean',
    ];

    /**
     * The polymorphic Seller whose invoice series this invoice belongs to: a
     * {@see Company} (distributor orders) or a {@see Distributor} (dealer
     * orders) (R21.5).
     */
    public function seller(): MorphTo
    {
        return $this->morphTo();
    }
}
