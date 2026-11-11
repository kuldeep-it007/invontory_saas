<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Log;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Region;
use Rosetta\Models\TaxType;

class Tax extends Model
{
    protected $table="region_category_taxes";
    protected $dateFormat = 'U';
    public function region()
    {
    	return $this->belongsTo(Region::class);
    }
    public function category()
    {
    	return $this->belongsTo(Category::class, 'category_code');
    }
    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_code');
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function taxType()
    {
        return $this->belongsTo(TaxType::class, 'tax_type_code','code');
    }
}
