<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Traits\Author;
use Rosetta\Traits\SettingsTrait;


class Transaction extends Model
{
    use SettingsTrait;
    use Author;
    protected $dateFormat = 'U';
    public function transactionable()
    {
        return $this->morphTo();
    }
    public function blockedItems()
    {
        return $this->hasMany(BlockedItem::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function blanketOrder()
    {
        return $this->belongsTo(BlanketOrder::class,'blanket_order_id');
    }
    public function advanceBlanketOrder()
    {
        return $this->belongsTo(AdvanceBlanketOrder::class,'blanket_order_id');
    }
}
