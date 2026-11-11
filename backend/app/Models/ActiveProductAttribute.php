<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Active_Product_Attribute model.
 *
 * One grant of catalog visibility: a single Buyer (Distributor or Dealer) is
 * permitted to see a single catalog attribute — a Category, Collection, or
 * Colour (R11.4). The presence of ANY rows for a Buyer switches that Buyer from
 * "sees everything" (R11.6) to "sees only the configured attributes" (R11.4,
 * R11.5); {@see \App\Services\CatalogVisibilityService} applies that rule.
 *
 * Tenant-owned and auditable (R34.1); resolved against the active tenant
 * database (no `tenant_id` column — isolation is physical).
 *
 * @property string $buyer_type
 * @property int $buyer_id
 * @property string $attribute_type
 * @property int $attribute_id
 */
class ActiveProductAttribute extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * The polymorphic morph aliases are the fully-qualified class names (no
     * custom morph map is registered), so configuration references the concrete
     * attribute classes directly.
     */
    public const ATTRIBUTE_CATEGORY = Category::class;

    public const ATTRIBUTE_COLLECTION = Collection::class;

    public const ATTRIBUTE_COLOUR = Colour::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_type',
        'buyer_id',
        'attribute_type',
        'attribute_id',
    ];

    /**
     * The Buyer (Distributor or Dealer) this visibility grant belongs to
     * (R11.4).
     */
    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The allowed catalog attribute: a Category, Collection, or Colour (R11.4).
     */
    public function attribute(): MorphTo
    {
        return $this->morphTo();
    }
}
