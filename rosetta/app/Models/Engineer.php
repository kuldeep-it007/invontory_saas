<?php 

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rosetta\Traits\Author;

class Engineer extends Model
{
	use SoftDeletes;
	use Author;
	protected $dateFormat = 'U';
	public function engineerable()
	{
		return $this->morphTo();
	}
}