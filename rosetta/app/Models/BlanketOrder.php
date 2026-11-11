<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;

class BlanketOrder extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $dateFormat = 'U';
   
	public function orderable()
	{
		return $this->morphTo()->withTrashed();
	}
    public function scopeConcrete($query)
    {
        return $query->whereNotNull('orderable_id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function purchase_orders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    public function purchase_lines()
    {
        return $this->hasManyThrough(PurchaseLine::class,PurchaseOrder::class);
    }
    public function net_amount()
    {
        return $this->purchase_lines()->sum('purchase_lines.amount');
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function getAmountAttribute()
    {
        return $this->purchase_orders()->active()->sum('Amount');
    }
    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }
    public function paid_amount()//#766
    {
        if($this->payments()->count())
            return $this->payments()->sum('amount');
        else
            return $this->po_payments()->sum('payments.amount');
    }
    public function po_payments()//#766
    {
        return $this->hasManyThrough('Rosetta\Models\Payment','Rosetta\Models\PurchaseOrder\PurchaseOrder','blanket_order_id','paymentable_id')
        ->where(
            'paymentable_type', 
            PurchaseOrder::class
        );
    }
    public function payment_status()
    {
        if($this->paid_amount() == 0)
            return 'Pending';
        elseif($this->paid_amount() == $this->amount)
            return 'Paid';
        else //  elseif($this->paid_amount() < $this->amount)
            return 'Partially Paid';
    }
    public function overdue()
    {
        return $this->hasOne(OverdueAmount::class, 'order_id');
    }
    public function company_orders()
    {
        return $this->purchase_orders()->whereNotNull('stock_point_id');
    }
    public function codistributor_orders()
    {
        return $this->purchase_orders()->whereHas('relatedOrder')->whereNull('stock_point_id');
    }
    public function co_super_stockist_orders()
    {
        return $this->purchase_orders()->whereHas('relatedOrder')->whereNull('stock_point_id');
    }
    public function distributor_orders()
    {
        return $this->purchase_orders()->whereNull('stock_point_id');
    }
    public function main_distributor_order()
    {
        return $this->purchase_orders()->whereNull('stock_point_id')->doesntHave('relatedOrder');
    }
    public function distributor_lines()
    {
        return $this->purchase_lines()->whereHas('purchase_order',function($q){
            $q->whereNull('stock_point_id');
        });
    }
    public function codistributor_lines()
    {
        return $this->codistributor_orders()->purchase_lines();
    }
    public function co_super_stockist_lines()
    {
        return $this->co_super_stockist_orders()->purchase_lines();
    }
    public function all_company_lines_shipped()
    {
        $shipped_company_orders =  $this->company_orders()->whereIn('status', ['Shipped', 'Complete']);

        $non_cancelled_company_orders = $this->company_orders()->whereNotIn('status', ['Cancelled']);

        if($shipped_company_orders->count() == $non_cancelled_company_orders->count())
            return true;
        else
            return false;
    }
    public function all_codistributor_lines_shipped()
    {
        $shipped_codistributors_orders =  $this->codistributor_orders()->whereIn('status', ['Shipped']);

        $non_cancelled_codistributors_orders = $this->codistributor_orders()->whereNotIn('status', ['Cancelled']);

        if($shipped_codistributors_orders->count() == $non_cancelled_codistributors_orders->count())
            return true;
        else
            return false;
    }
    public function all_co_super_stockist_lines_shipped()
    {
        $shipped_co_super_stockists_orders =  $this->co_super_stockist_orders()->whereIn('status', ['Shipped']);

        $non_cancelled_co_super_stockists_orders = $this->co_super_stockist_orders()->whereNotIn('status', ['Cancelled']);

        if($shipped_co_super_stockists_orders->count() == $non_cancelled_co_super_stockists_orders->count())
            return true;
        else
            return false;
    }
    public function remarks()
    {      
        return $this->purchase_orders()->pluck('special_request')->first();
    }
    public function getStatusAttribute()
    {
        $status = 'Open';
        $valid_orders = $this->purchase_orders()->active()->get();
        //$valid_orders->groupBy('') //ss_stock_point_id
        $groupedArrayKeysForSsStockPoint = $valid_orders->pluck('ss_stock_point_id')->unique()->implode(',');
        $groupedArrayKeysForDistStockPoint = $valid_orders->pluck('dist_stock_point_id')->unique()->implode(',');
        
        if($this->orderable && $this->orderable->distributor)
        {
            if($valid_orders->isEmpty())
            {
                $status = 'Cancelled';
            }
            else if($valid_orders->whereIn('status',['Shipped','Complete'])->count() == $valid_orders->count())
            {
                $status = 'Shipped';
            }
            else if($valid_orders->whereIn('status',['Complete','Shipped','Partially Shipped'])->count())
            {
                $status = 'Partially Shipped';
            }
            else
                $status = 'Open';
        }
        else if($this->distributor_id)
        {
            if($valid_orders->isEmpty())
            {
                $status = 'Cancelled';
            }
            else if($valid_orders->whereIn('status',['Shipped','Complete'])->count() == $valid_orders->count())
            {
                $status = 'Shipped';
            }
            else if($valid_orders->whereIn('status',['Complete','Shipped','Partially Shipped'])->count())
            {
                $status = 'Partially Shipped';
            }
            else
                $status = 'Open';
        }
        else
        {
            
            if($valid_orders->isEmpty())
            {
                $status = 'Cancelled';
            }
            else if($valid_orders->where('status','=','Shipped')->count() == $valid_orders->count())
            {
                $status = 'Shipped';
            }
            else if($valid_orders->where('status','=','Shipped')->count() && (
               $valid_orders->whereIn('status',['Shipped','Complete'])->count() == $valid_orders->count() 
            ))
            {
                $status = 'Shipped';

            }
            else if($valid_orders->where('status','=','Complete')->count() == $valid_orders->count())
            {
                $status = 'Complete';
            }
            else if((! $valid_orders->where('status','=','Partially Shipped')->isEmpty()) || ($valid_orders->whereIn('status',['Shipped','Complete'])->count() && $valid_orders->whereIn('status',['Shipped','Complete'])->count()<$valid_orders->count()))
            {
                $status = 'Partially Shipped';
            }
            else if($valid_orders->where('status','=','Complete')->isNotEmpty() && ($valid_orders->where('status','=','Complete')->count() + $valid_orders->where('status','=','Pending_Approval')->count() == $valid_orders->count()))
            {
                $status = 'Partially Shipped';
            }
            else if($valid_orders->where('status','=','Pending_Approval')->count() == $valid_orders->count())
            {
                $status = 'Pending_Approval';
            }
            else if($valid_orders->where('status','=','Approved')->count() == $valid_orders->count())
            {
                $status = 'Approved';
            }
            else
            {
                $status = "Open";
            }
        }        
        if (empty($groupedArrayKeysForSsStockPoint) && empty($groupedArrayKeysForDistStockPoint)) {
            $this->order_status = $status;
            if ($this->order_status == 'Shipped') {
                $this->shipped = true;
            } else {
                $this->shipped = false;
            }
            $this->save();
            return $status;
        }else{
            return $this->order_status;
        }        
        //return 'UNKNOWN';
    }

    public function relatedOrder(){
        return $this->belongsTo(PurchaseOrder::class,'related_po_id')->withTrashed();
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }
    public function nav_invoice_numbers()
    {
        $valid_orders = $this->purchase_orders()->active()->get();
        $uniqueInvoiceNumbers=[];

        foreach ($valid_orders as $valid_order)
        {
            $uniqueInvoiceNumbers[] = $valid_order->nav_invoice_number;
        }
        $ros_invoice_numbers = implode(',', $uniqueInvoiceNumbers);
        return $ros_invoice_numbers;
    }
    public function superStockist()
    {
        return $this->belongsTo(SuperStockist::class);
    }    
}
