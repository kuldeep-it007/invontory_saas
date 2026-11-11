<?php

namespace Rosetta\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;

class ActiveProductAttributes extends Model
{
	use SoftDeletes;
    protected $dateFormat = 'U';
    protected $fillable = ['id', 'activable_id', 'activable_type', 'company_id', 'category_code', 'category_display', 'collection_code', 'collection_display', 'colour_code', 'colour_display', 'created_at', 'updated_at', 'deleted_at', 'is_moved_from_pd', 'is_moved_from_md'];
    public function activable()
    {
        return $this->morphTo();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
