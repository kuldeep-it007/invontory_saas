<?php 

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Traits\Author;

class TempDealer extends Model
{
	use Author;
    protected $dateFormat = 'U';
    public function company()
    {
    	return $this->belongsTo(Company::class);
    }
    public function distributor()
    {
    	return $this->belongsTo(Distributor::class);
    }
}