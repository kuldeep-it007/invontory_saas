<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use OwenIt\Auditing\Contracts\Auditable;

class Payment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    
    public function dealer(){
        return $this->belongsTo(Dealer::class,'customer_id');
    }
    public function paymentable()
    {
        return $this->morphTo();
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
