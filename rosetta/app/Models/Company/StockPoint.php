<?php

namespace Rosetta\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Company\CustomerStockPoint;

class StockPoint extends Model
{
    protected $dateFormat = 'U';
    protected $table = 'stock_points';
    public function company()
    {
    	return $this->belongsTo(Company::class);
    }

    public function all_stock_points()
    {
        return $this->hasMany(CustomerStockPoint::class);
    }
    public function customer_stock_points()
    {
        return $this->all_stock_points()->where('customerable_type','!=',Company::class);
    }
}
