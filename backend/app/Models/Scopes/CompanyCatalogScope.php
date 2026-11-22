<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CatalogOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Query-level catalogue isolation for the Company.
 *
 * A Company user sees only Company-owned catalogue rows; distributor-owned
 * categories, collections, colours and products do not exist for it (lists
 * omit them, route model binding answers 404). Distributor and Dealer users
 * see the whole network catalogue; what they may MANAGE is enforced separately
 * by {@see CatalogOwner::assertManages()}. Queries outside an authenticated
 * request (queue jobs, console) are not scoped.
 */
class CompanyCatalogScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (CatalogOwner::isCompanyUser(CatalogOwner::currentUser())) {
            $builder->where($model->qualifyColumn('owner_key'), CatalogOwner::COMPANY);
        }
    }
}
