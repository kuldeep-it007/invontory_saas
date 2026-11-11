<?php 

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Traits\Author;

class Message extends Model
{
	use Author;
    protected $dateFormat = 'U';
    public function messageable()
    {
    	return $this->morphTo();
    }
}