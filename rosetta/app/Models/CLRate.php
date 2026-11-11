<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use OwenIt\Auditing\Contracts\Auditable;

class CLRate extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
	protected $table = 'cl_rates';
	public function cl_rates()
    {
        return $this->morphTo(CLRate::class,'CLable_type','CLable_id');
    }

    public function category()
    {
    	return $this->belongsTo(Category::class,'category_code');
    }
    public function collection()
    {
    	return $this->belongsTo(Collection::class,'collection_code');
    }
    public function colour()
    {
    	return $this->belongsTo(Colour::class,'colour_code');
    }
}