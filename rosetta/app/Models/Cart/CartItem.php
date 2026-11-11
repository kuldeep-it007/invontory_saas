<?php

namespace Rosetta\Models\Cart;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\NAV\ILEServiceInterface;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartItem extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable, SoftDeletes;
    protected $dateFormat = 'U';
    protected $ileService;
    public function itemable()
	{
		return $this->morphTo();
	}
	public function product()
	{
		return $this->belongsTo(Product::class);
	}
	public function distributor()
	{
		return $this->belongsTo(Distributor::class, 'itemable_id');
	}
	public function dealer()
	{
		return $this->belongsTo(Dealer::class, 'itemable_id');
	}
	public function superStockist()
	{
		return $this->belongsTo(SuperStockist::class, 'itemable_id');
	}
	public function getStockAttribute()
	{
		$companyId = $this->distributor->company_id;
		if(!$companyId)
			$companyId = $this->dealer->company_id;
		$company = Company::find($companyId);
		$ileService = resolve('Rosetta\Services\NAV\ILEServiceInterface');
		//return $ileService->getBatchSpecificStock($company, $this->product_id, $this->batch_no);
		return $ileService->getBatchSpecificStock($company, $this->product->nav_id, $this->batch_no);
	}
	public function getBlockedQuantityAttribute()
	{
		$blockedItems = BlockedItem::where('product_id', '=', $this->product_id)->get();
		$blockedQuantity = 0;
		foreach ($blockedItems as $blockedItem)
		{
			$blockedQuantity = $blockedQuantity + $blockedItem->quantity;
		}
		return $blockedQuantity;
	}
	public function stock_point()
	{
		return $this->belongsTo(StockPoint::class);
	}
}
