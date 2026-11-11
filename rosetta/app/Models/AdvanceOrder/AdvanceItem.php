<?php

namespace Rosetta\Models\AdvanceOrder;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Message;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;

class AdvanceItem extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use SoftDeletes;
    protected $dateFormat = 'U';
    protected $dates = ['expires_at'];

    public function advanceable()
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
		return $this->belongsTo(Distributor::class, 'advanceable_id');
	}
	public function dealer()
	{
		return $this->belongsTo(Dealer::class, 'advanceable_id');
	}
	public function superStockist()
	{
		return $this->belongsTo(SuperStockist::class, 'advanceable_id');
	}
	public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }
    public function stock_point()
    {
		return $this->belongsTo(StockPoint::class);
	}
}
