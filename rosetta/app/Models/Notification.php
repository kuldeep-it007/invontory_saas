<?php 

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\User;
use Rosetta\Traits\Author;

class Notification extends Model
{
	use Author;
    protected $dateFormat = 'U';

    public function recipient()
    {
    	return $this->belongsTo(User::class, 'recipient_id');
    }
    public function sender()
    {
    	return $this->belongsTo(User::class, 'sender_id');
    }
}