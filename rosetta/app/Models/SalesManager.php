<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesManager extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    protected $fillable = ['name', 'status'];   
}
