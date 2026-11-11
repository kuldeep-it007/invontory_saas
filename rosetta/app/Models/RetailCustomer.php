<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rosetta\Models\Company;
use Rosetta\Models\State;
use Rosetta\NAV\WS\Customer\Customer_Card;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Traits\Author;

class RetailCustomer extends Model
{
	use SoftDeletes;
	use Author;
    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    protected $customerService;
    protected $customerCard;

    public function company()
    {
    	return $this->belongsTo(Company::class);
    }
    public function getNavAttribute()
    {
        if(!$this->customerCard)
        {
            $customerService = resolve(CustomerServiceInterface::class);
            $this->customerCard = $customerService->get($this->company,$this->nav_id);
        }
        return $this->customerCard;
    }
    public function setCustomerCard(Customer_Card $customerCard)
    {
        $this->customerCard = $customerCard;
    }
    public function state()
    {
        return $this->belongsTo(State::class,'state_code');
    }
}
