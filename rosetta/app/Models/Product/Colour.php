<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;

class Colour extends Model
{
    protected $dateFormat = 'U';
	protected $table = 'colours';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;
    public function scopeActive($query)
    {
    	$query->where('active','=',true);
    }
}
