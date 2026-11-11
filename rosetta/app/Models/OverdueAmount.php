<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use OwenIt\Auditing\Contracts\Auditable;

class OverdueAmount extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
	protected $dates = ['overdue_date'];
    protected $dateFormat = 'U';
    
    public function overdueable()
    {
        return $this->morphTo();
    }
}
