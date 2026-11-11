<?php

namespace Rosetta\Models\AdvanceOrder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Address;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Log;

class AdvanceOrder extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $dateFormat = 'U';
   
    public function advance_lines()
	{
		return $this->hasMany(AdvanceLine::class);
	}
	public function addresses()
    {
    	return $this->morphMany(Address::class, 'addressable');
    }
    public function orderable()
	{
		return $this->morphTo();
	}
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function stock_point()
    {
        return $this->belongsTo(StockPoint::class);
    }
    public function relatedOrder()
    {
        return $this->hasOne(AdvanceBlanketOrder::class,'related_ao_id');
    }
    public function scopeActive($query)
    {
        return $query->where('status','<>','Cancelled');
    }
    public function advance_blanket_order()
    {
        return $this->belongsTo(AdvanceBlanketOrder::class, 'blanket_order_id');
    }
}
