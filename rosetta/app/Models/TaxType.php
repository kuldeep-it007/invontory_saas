<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Region;

class TaxType extends Model
{
	protected $table = 'tax_types';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $dateFormat = 'U';
    public function regions()
    {
    	return $this->belongsToMany(Region::class);
    }
}
