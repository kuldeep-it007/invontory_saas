<?php

namespace Rosetta\Models\Invoice;

use Illuminate\Database\Eloquent\Model;

class GeneralProductPostingGroup extends Model
{
    protected $dateFormat = 'U';
	protected $table = 'general_product_posting_group';
    protected $primaryKey = 'code';
    public $incrementing = false;
}
