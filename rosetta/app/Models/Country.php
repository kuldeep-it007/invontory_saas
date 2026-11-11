<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
	protected $dateFormat = 'U';
	protected $table = 'countries';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    public function states()
	{
		return $this->hasMany(State::class);
	}
}