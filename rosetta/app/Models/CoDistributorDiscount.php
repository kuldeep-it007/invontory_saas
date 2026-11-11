<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Dealer;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use OwenIt\Auditing\Contracts\Auditable;

class CoDistributorDiscount extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	protected $dateFormat = 'U';
	protected $table = 'co_distributor_discounts';
	public function coDistributor(){
		return $this->belongsTo(Distributor::class,'customer_id');
	}
	public function parentistributor(){
		return $this->belongsTo(Distributor::class,'distributor_id');
	}
	public function category()
	{
		return $this->belongsTo(Category::class,'category_code');
	}
	public function collection()
	{
		return $this->belongsTo(Collection::class,'collection_code');
	}
	public function colour()
	{
		return $this->belongsTo(Colour::class,'colour_code');
	}
}