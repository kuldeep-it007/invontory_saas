<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\Country;
use Rosetta\Models\Discount;
use Rosetta\Models\Product\Tax;
use Rosetta\Models\TaxType;

class Region extends Model
{
    protected $dateFormat = 'U';
    public function taxTypes()
    {
    	return $this->belongsToMany(TaxType::class);
    }
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    public function companies()
    {
    	return $this->hasMany(Company::class);
    }
    public function getUOM($type,$from)
    {
        return DB::table('uom_mappings')
            ->select('id','from','to','conversion_rate')
            ->where('type','=',$type)
            ->where('region_id','=',$this->id)
            ->where('from','=',$from)//#773
            ->first();
    }
    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }
    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
 /*   public function scopeCategoryTaxes($query,)
    {

    }*/
}
