<?php

namespace Rosetta\Models\AdvanceOrder;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Transaction;

class BlockedAdvanceItem extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    protected $dates = ['expires_at', 'cool_down_start_time','cool_down_end_time'];
    public function blockable()
	{
		return $this->morphTo();
	}
	public function product()
	{
		return $this->belongsTo(Product::class);
	}
	public function scopeActive($query)
	{
		return $query->where('expires_at','>',Carbon::now()->format('U'));
	}
	public function company()
	{
		return $this->belongsTo(Company::class);
	}
	public function distributor()
	{
		return $this->belongsTo(Distributor::class, 'blockable_id');
	}
	public function dealer()
	{
		return $this->belongsTo(Dealer::class, 'blockable_id');
	}
	public function superStockist()
	{
		return $this->belongsTo(SuperStockist::class, 'blockable_id');
	}
	public function stock_point()
	{
		return $this->belongsTo(StockPoint::class);
	}
	public function transaction()
	{
		return $this->belongsTo(Transaction::class);
	}
}
