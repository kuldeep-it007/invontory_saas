<?php

namespace Rosetta\Models\Invoice;

use Illuminate\Database\Eloquent\Model;

class InventoryPostingGroup extends Model
{
    protected $dateFormat = 'U';
	protected $table = 'inventory_posting_group';
    protected $primaryKey = 'code';
    public $incrementing = false;
}
