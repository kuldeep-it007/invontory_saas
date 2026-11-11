<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Company;
use Rosetta\Models\Log;
use Rosetta\Models\Product\Product;
use OwenIt\Auditing\Contracts\Auditable;

class Price extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table="product_prices";
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'loggable');
    }
}
