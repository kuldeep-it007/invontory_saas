<?php

namespace Rosetta\Models\PurchaseOrder;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Log;
use Rosetta\Models\Product\Product;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\ReturnItem;
use OwenIt\Auditing\Contracts\Auditable;

class PurchaseLine extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    public function purchase_order()
    {
    	return $this->belongsTo(PurchaseOrder::class);
    }
    public function product()
    {
    	return $this->belongsTo(Product::class);
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function returnItems()
    {
        return $this->morphMany(ReturnItem::class, 'returnable');
    }
    public function approvedReturnItems()
    {
        return $this->morphMany(ReturnItem::class, 'returnable')->where('status', '=', 'Approved');
    }
    public function pendingReturnItems()
    {
        return $this->morphMany(ReturnItem::class, 'returnable')->where('status', '=', 'Pending');
    }
    public function rejectedReturnItems()
    {
        return $this->morphMany(ReturnItem::class, 'returnable')->where('status', '=', 'Rejected');
    }
    public function getIsCompanyLineAttribute()
    {
        return $this->purchase_order->navision_order_number != null;
    }
    public function getIsDistributorLineAttribute()
    {
        return $this->purchase_order->distributor_id && !$this->purchase_order->super_stockist_id;
    }
    public function getIsSuperStockistLineAttribute()
    {
        return $this->purchase_order->super_stockist_id && !$this->purchase_order->distributor_id;
    }
}
