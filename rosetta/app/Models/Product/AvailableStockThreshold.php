<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Product\Product;

class AvailableStockThreshold extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';

	public function product()
    {
    	return $this->belongsTo(Product::class);
    }
    public function stock_point()
    {
        return $this->belongsTo(StockPoint::class);
    }
}
