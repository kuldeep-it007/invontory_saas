<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
	protected $dateFormat = 'U';
	protected $table = 'states';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    public function cities()
	{
		return $this->hasMany(City::class);
	}
	public function country()
    {
    	return $this->belongsTo(Country::class);
    }
}