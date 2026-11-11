<?php

namespace Rosetta\Models\Invoice;

use Illuminate\Database\Eloquent\Model;

class VatProductPostingGroup extends Model
{
    protected $dateFormat = 'U';
	protected $table = 'vat_product_posting_group';
    protected $primaryKey = 'code';
    public $incrementing = false;
}
