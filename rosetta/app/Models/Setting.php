<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['key','value','type'];
    public function settings()
    {
        return $this->morphTo();
    }
}
