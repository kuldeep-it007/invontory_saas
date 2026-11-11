<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\DistributorPrice;
use Rosetta\Models\Product\Price;
use Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    protected $productService;
    protected $itemCardIntransit;
    protected static function boot()
    {
        parent::boot();

        /*static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('status', 1);
        });*/
    }
    public function category()
    {
    	return $this->belongsTo(Category::class);
    }
    public function collection()
    {
    	return $this->belongsTo(Collection::class);
    }
    public function colour()
    {
    	return $this->belongsTo(Colour::class);
    }
    public function getNavAttribute()
    {
        if(!$this->itemCardIntransit)
        {
            $productService = resolve(ItemCardIntransitServiceInterface::class);
            $companyId = config('nav.default_company');
            $company = Company::find($companyId);
            $this->itemCardIntransit = $productService->get($company,$this->nav_id);
        }
        return $this->itemCardIntransit;
    }
    public function setItemCardIntransit(Item_Card_Intransit $itemCardIntransit)
    {
        $this->itemCardIntransit = $itemCardIntransit;
    }
    public function prices()
    {
        return $this->hasMany(Price::class);
    }
    public function getImageAttribute()
    {
        $cmsService = resolve('Rosetta\Services\CMS\CMSServiceInterface');
        return $cmsService->getProductImage($this->nav_id);
    }
}
