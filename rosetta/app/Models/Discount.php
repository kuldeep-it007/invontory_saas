<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Dealer;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Region;
use Rosetta\Models\State;
use Rosetta\Models\SuperStockist\SuperStockist;

class Discount extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	protected $dateFormat = 'U';
	public function region()
	{
		return $this->belongsTo(Region::class);
	}
	public function distributor()
	{
		return $this->belongsTo(Distributor::class, 'customer_id');
	}
	public function dealer()
	{
		return $this->belongsTo(Dealer::class, 'customer_id');
	}

	public function distributorunderss()
	{
		return $this->belongsTo(Distributor::class, 'customer_id');
	}

	public function superstockist()
	{
		return $this->belongsTo(SuperStockist::class, 'customer_id');
	}

	public function dealerunderdistributorunderss()
	{
		return $this->belongsTo(Dealer::class, 'customer_id');
	}

	public function category()
	{
		return $this->belongsTo(Category::class, 'category_code');
	}
	public function collection()
	{
		return $this->belongsTo(Collection::class, 'collection_code');
	}
	public function colour()
	{
		return $this->belongsTo(Colour::class, 'colour_code');
	}
	public function state()
	{
		return $this->belongsTo(State::class, 'state_code');
	}
}
