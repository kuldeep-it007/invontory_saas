<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Traits\Author;

class Log extends Model
{
    use Author;
    protected $dateFormat = 'U';
    public function loggable()
    {
    	return $this->morphTo();
    }
}
