<?php 

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Address extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	protected $dateFormat = 'U';
	public function addressable()
	{
		return $this->morphTo();
	}
}