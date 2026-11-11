<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Colour model.
 *
 * Leaf level of the catalog hierarchy Category → Collection → Colour (R11.2).
 * Each Colour belongs to exactly one Collection. Tenant-owned and auditable
 * (R34.1); resolved against the active tenant database.
 */
class Colour extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection_id',
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

    /** Only active colours (Rosetta-style `active` flag). */
    public function scopeActive($query)
    {
        return $query->where('active', true);
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
     * The products carrying this colour (R11.2).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
