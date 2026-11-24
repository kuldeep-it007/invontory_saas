<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Distributor;
use App\Models\Scopes\CompanyCatalogScope;
use App\Support\CatalogOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catalogue ownership for categories, collections, colours and products.
 *
 * Keeps `owner_key` (owner_distributor_id ?? 0) in sync for the per-owner
 * unique indexes and applies {@see CompanyCatalogScope}. Neither owner column
 * is mass-assignable; services set `owner_distributor_id` from the actor.
 */
trait HasCatalogOwner
{
    public static function bootHasCatalogOwner(): void
    {
        static::addGlobalScope(new CompanyCatalogScope);

        static::saving(function (self $model): void {
            $model->setAttribute('owner_key', CatalogOwner::keyOf($model));
        });
    }

    /**
     * The owning distributor; null when the Company owns the row.
     *
     * @return BelongsTo<Distributor, $this>
     */
    public function ownerDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'owner_distributor_id');
    }

    /**
     * Rows owned by one owner key (0 = Company).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOwnedBy(Builder $query, int $ownerKey): Builder
    {
        return $query->where($this->qualifyColumn('owner_key'), $ownerKey);
    }
}
