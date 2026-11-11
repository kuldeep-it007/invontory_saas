<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\SuperStockist\SuperStockist;


class AdvanceBlanketOrder extends Model
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
    public function advance_orders()
    {
        return $this->hasMany(AdvanceOrder::class, 'blanket_order_id');
    }
    public function advance_lines()
    {
        return $this->hasManyThrough(AdvanceLine::class,AdvanceOrder::class,'blanket_order_id');
    }
    public function non_cancelled_advance_lines()
    {
        return $this->advance_lines()->whereHas('advance_order',function($q){
            $q->whereNotIn('status', ['Cancelled']);
        });
    }
    public function net_amount()
    {
        return $this->non_cancelled_advance_lines()->sum('advance_lines.amount');
       // return $this->advance_lines()->sum('advance_lines.amount');
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function getAmountAttribute()
    {
        return $this->advance_orders()->active()->sum('amount');
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
            return $this->ao_payments()->sum('payments.amount');
    }
    public function ao_payments()//#766
    {
        return $this->hasManyThrough('Rosetta\Models\Payment','Rosetta\Models\AdvanceOrder\AdvanceOrder','blanket_order_id','paymentable_id')
        ->where(
            'paymentable_type', 
            AdvanceOrder::class
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
    public function all_company_lines_shipped()
    {
        $shipped_company_orders =  $this->advance_orders()->whereIn('status', ['Shipped', 'Complete']);

        $non_cancelled_company_orders = $this->advance_orders()->whereNotIn('status', ['Cancelled']);

        if($shipped_company_orders->count() == $non_cancelled_company_orders->count())
            return true;
        else
            return false;
    }
    public function remarks()
    {      
        return $this->advance_orders()->pluck('special_request')->first();
    }
    public function getStatusAttribute()
    {
        $status = 'Open';

        if($this->orderable && $this->orderable->distributor)
        {
            $valid_orders = $this->advance_orders()->active()->get();
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
            $valid_orders = $this->advance_orders()->active()->get();
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
            $valid_orders = $this->advance_orders()->active()->get();
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
        $this->order_status = $status;
        if($this->order_status == 'Shipped')
        {
            $this->shipped = true;
        }
        else
        {
            $this->shipped = false;
        }
            
        $this->save();
        return $status;
    }

    public function relatedOrder(){
        return $this->belongsTo(AdvanceOrder::class,'related_ao_id')->withTrashed();
    }
    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }
    public function superStockist()
    {
        return $this->belongsTo(SuperStockist::class);
    }
    public function nav_invoice_numbers()
    {
        $valid_orders = $this->advance_orders()->active()->get();
        $uniqueInvoiceNumbers=[];

        foreach ($valid_orders as $valid_order)
        {
            $uniqueInvoiceNumbers[] = $valid_order->nav_invoice_number;
        }
        $ros_invoice_numbers = implode(',', $uniqueInvoiceNumbers);
        return $ros_invoice_numbers;
    }
}
