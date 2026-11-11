<?php

namespace Rosetta\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;

class BillingLocation extends Model
{
    protected $dateFormat = 'U';
    public function company()
    {
    	return $this->belongsTo(Company::class);
    }
}
