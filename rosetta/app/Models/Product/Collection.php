<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Product\Tax;

class Collection extends Model
{
    protected $dateFormat = 'U';
	protected $table = 'collection_codes';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;
    public function scopeActive($query)
    {
    	$query->where('active','=',true);
    }
    public function getTechspecsAttribute()
    {
    	return 100;
    }
    public function colours()
    {
        return $this->hasMany(Colour::class);
    }
    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }
}
