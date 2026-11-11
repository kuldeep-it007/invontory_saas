<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use OwenIt\Auditing\Contracts\Auditable;

class Target extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    
    public function targetable()
    {
        return $this->morphTo();
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
