<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Tax;

class Category extends Model
{
    protected $dateFormat = 'U';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    protected $table = 'category_codes';
    public $incrementing = false;
    public function scopeActive($query)
    {
    	$query->where('active','=',true);
    }
    public function collections()
    {
    	return $this->hasMany(Collection::class);

    }
    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }
}
