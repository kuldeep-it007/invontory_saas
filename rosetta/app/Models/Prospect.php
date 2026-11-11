<?php

namespace Rosetta\Models;

use Rosetta\Traits\Author;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Prospect extends  Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes { restore as private restoreB; }

    public function restore()
    {
        $this->restoreA();
        $this->restoreB();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
   
   
}//#785
