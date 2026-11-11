<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\SuperStockist\SuperStockist;

class SSDiscount extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	protected $dateFormat = 'U';
	protected $table = 'ss_discounts';
	public function superStockist(){
		return $this->belongsTo(SuperStockist::class,'customer_id');
	}
	public function parentSuperStockist(){
		return $this->belongsTo(SuperStockist::class,'super_stockist_id');
	}
	public function distributor(){
		return $this->belongsTo(Distributor::class,'customer_id');
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