<?php

namespace Rosetta\Models\PurchaseOrder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Log;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\Payment;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\SuperStockist\SuperStockist;

class PurchaseOrder extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $dateFormat = 'U';
   
    public function purchase_lines()
	{
		return $this->hasMany(PurchaseLine::class);
	}
    public function net_amount()
    {
        return $this->purchase_lines()->sum('amount');
    }
	public function addresses()
    {
    	return $this->morphMany(Address::class, 'addressable');
    }
    public function orderable()
	{
		return $this->morphTo()->withTrashed();
	}
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }
    public function paid_amount()
    {
        return $this->payments()->sum('amount');
    }
    public function overdue()
    {
        return $this->hasOne(OverdueAmount::class, 'order_id');
       // return $this->orderable()->overdue_amounts();
    }
    public function blanket_order()
    {
        return $this->belongsTo(BlanketOrder::class);
    }
    public function scopeActive($query)
    {
        return $query->where('status','<>','Cancelled');
    }
    public function stock_point()
    {
        return $this->belongsTo(StockPoint::class);
    }
    public function relatedOrder()
    {
        return $this->hasOne(BlanketOrder::class,'related_po_id');
    }
    public function superStockist()
    {
        return $this->belongsTo(SuperStockist::class,'super_stockist_id');
    }
}
