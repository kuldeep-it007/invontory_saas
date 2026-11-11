<?php

namespace Rosetta\Models\Company;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company\StockPoint;
use OwenIt\Auditing\Contracts\Auditable;

class CustomerStockPoint extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    protected $table = 'customer_stock_points';
    public $timestamps = false;
    
    public function customerable()
	{
		return $this->morphTo();
	}
	public function stock_point()
	{
		return $this->belongsTo(StockPoint::class);
	}
}