<?php
namespace Rosetta\Traits;

use Rosetta\Models\User;

Trait Author
{
	public function author()
	{
		return $this->belongsTo(User::class,'created_by')->withTrashed();
	}
}