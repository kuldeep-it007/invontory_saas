<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;

class Stock extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    protected $blocked;
	public function stockable()
	{
		return $this->morphTo();
	}
	public function product()
    {
    	return $this->belongsTo(Product::class);
    }
    public function distributor()
    {
    	return $this->belongsTo(Distributor::class,'stockable_id');
    }
    public function superStockist()
    {
        return $this->belongsTo(SuperStockist::class,'stockable_id');
    }
    public function scopeDistributors($query)
    {
		$query->whereStockableType(Distributor::class);
    }
    public function setBlocked($blocked)
    {
        $this->blocked = $blocked;
    }
    public function getBlocked()
    {
        return $this->blocked;
    }
}
