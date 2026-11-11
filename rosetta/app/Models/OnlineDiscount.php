<?php

namespace Rosetta\Models;

use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class OnlineDiscount extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    protected $table = 'online_discounts';
    public function discountable()
    {
        return $this->morphTo();
    }
    public function category()
    {
        return $this->belongsTo(Category::class,'category_code');
    }
    public function collection()
    {
        return $this->belongsTo(Collection::class,'collection_code')
            ->where('category_code','=',$this->category_code);
    }
    public function colour()
    {
        return $this->belongsTo(Colour::class,'colour_code')
            ->where('collection_code','=',$this->collection_code);
    }
}
