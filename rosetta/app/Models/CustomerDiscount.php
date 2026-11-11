<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Dealer;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use OwenIt\Auditing\Contracts\Auditable;

class CustomerDiscount extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	protected $dateFormat = 'U';
	protected $table = 'customer_discounts';
	public function distributor(){
		return $this->belongsTo(Distributor::class,'customer_id');
	}
	public function parentDistributor(){
		return $this->belongsTo(Distributor::class,'distributor_id');
	}
	public function dealer(){
		return $this->belongsTo(Dealer::class,'customer_id');
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