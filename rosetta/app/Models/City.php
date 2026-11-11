<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
	protected $dateFormat = 'U';
	protected $table = 'cities';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;
	public function state()
    {
    	return $this->belongsTo(State::class);
    }
}