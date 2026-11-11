<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Product model.
 *
 * A sellable catalog item carrying SKU, tax type, and unit of measure (R11.2),
 * referencing its Collection and Colour (the owning Category is reached through
 * the Collection). SKU is UNIQUE within the tenant database (R11.1); because
 * isolation is physical that uniqueness is per-tenant automatically.
 *
 * Tenant-owned and auditable (R34.1); resolved against the active tenant
 * database (no `tenant_id` column).
 */
class Product extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sku',
        'name',
        'price',
        'collection_id',
        'colour_id',
        'tax_type',
        'uom',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Keep the monetary value exact as a string (DECIMAL(15,2)).
            'price' => 'decimal:2',
        ];
    }

    /**
     * The owning collection (R11.2).
     *
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * The product's colour (R11.2).
     *
     * @return BelongsTo<Colour, $this>
     */
    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class);
    }
}
