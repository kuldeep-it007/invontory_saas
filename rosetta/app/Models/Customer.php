<?php 

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Traits\Author;

class Customer extends Model
{
	use Author;
	protected $dateFormat = 'U';
	public function customerable()
	{
		return $this->morphTo();
	}
}