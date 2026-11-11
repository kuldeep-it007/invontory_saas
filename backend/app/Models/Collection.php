<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Collection model.
 *
 * Middle level of the catalog hierarchy Category → Collection → Colour
 * (R11.2). Each Collection belongs to exactly one Category. Tenant-owned and
 * auditable (R34.1); resolved against the active tenant database.
 */
class Collection extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
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

    /** Only active collections (Rosetta-style `active` flag). */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * The owning category (R11.2).
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The colours grouped under this collection (R11.2).
     *
     * @return HasMany<Colour, $this>
     */
    public function colours(): HasMany
    {
        return $this->hasMany(Colour::class);
    }

    /**
     * The products belonging to this collection (R11.2).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
