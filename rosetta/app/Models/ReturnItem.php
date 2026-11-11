<?php

namespace Rosetta\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\SuperStockist\SuperStockist;

class ReturnItem extends Model
{
    protected $dateFormat = 'U';
    protected $dates = ['authorisation_date', 'return_till'];

    public function returnable()
	{
		return $this->morphTo();
	}
	public function product()
	{
		return $this->belongsTo(Product::class);
	}
	public function company()//company stock returned by customer (customer as of now is Dealer)
	{
		return $this->belongsTo(Company::class);
	}
	public function distributor()//distributor stock returned by customer (customer as of now is Dealer)
	{
		return $this->belongsTo(Distributor::class);
	}
	public function purchaseLine()
	{
		return $this->belongsTo(PurchaseLine::class, 'returnable_id');
	}
	public function dealer()//dealer customer
	{
        return $this->belongsTo(Dealer::class,'customer_id');
    }
    public function superStockist()//super stockist stock returned by customer (customer as of now is Distributor)
	{
		return $this->belongsTo(SuperStockist::class);
	}
}
