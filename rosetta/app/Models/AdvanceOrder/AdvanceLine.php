<?php

namespace Rosetta\Models\AdvanceOrder;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\Log;
use Rosetta\Models\Message;
use Rosetta\Models\Product\Product;
use OwenIt\Auditing\Contracts\Auditable;

class AdvanceLine extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $dateFormat = 'U';
    public function advance_order()
    {
    	return $this->belongsTo(AdvanceOrder::class);
    }
    public function product()
    {
    	return $this->belongsTo(Product::class);
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }
}
