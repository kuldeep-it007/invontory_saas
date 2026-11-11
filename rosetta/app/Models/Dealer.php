<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\AdvanceOrder\BlockedAdvanceItem;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\CustomerStockPoint;
use Rosetta\Models\Customer;
use Rosetta\Models\Distributor;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\State;
use Rosetta\Models\User;
use Rosetta\Models\blockedAdvanceItems;
use Rosetta\NAV\WS\Customer\Customer_Card;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Traits\Author;
use Rosetta\Traits\TransactionTrait;

class Dealer extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    use Author;
    use TransactionTrait;
    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    protected $customerService;
    protected $customerCard;
    protected $fillable = ['nav_id', 'name', 'contact', 'phone', 'email', 'gstin', 'area', 'updated_folder_given', 'app_explained', 'notes', 'company_id', 'distributor_id', 'payment_type', 'type', 'credit_limit', 'overdue_balance', 'overdue_hold_days', 'created_by', 'created_at', 'updated_at', 'deleted_at', 'address', 'address_2', 'city_code', 'state_code', 'post_code', 'is_moved_from_pd', 'is_moved_from_md', 'priority', 'sales_manager_id'];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }
    public function getNavAttribute()
    {
        if (!$this->customerCard) {
            $customerService = resolve(CustomerServiceInterface::class);
            $this->customerCard = $customerService->get($this->company, $this->nav_id);
        }
        return $this->customerCard;
    }
    public function setCustomerCard(Customer_Card $customerCard)
    {
        $this->customerCard = $customerCard;
    }
    public function stocks()
    {
        return $this->morphMany(Stock::class, 'stockable');
    }
    public function cartItems()
    {
        return $this->morphMany(CartItem::class, 'itemable')->orderBy('created_at', 'desc');
    }
    public function blockedItems()
    {
        return $this->morphMany(BlockedItem::class, 'blockable');
    }
    public function blockedAdvanceItems()
    {
        return $this->morphMany(BlockedAdvanceItem::class, 'blockable');
    }
    public function orders()
    {
        return $this->morphMany(PurchaseOrder::class, 'orderable');
    }
    public function advanceOrders()
    {
        return $this->morphMany(AdvanceOrder::class, 'orderable');
    }
    public function advanceItems()
    {
        return $this->morphMany(AdvanceItem::class, 'advanceable');
    }
    public function activeProductAttributes()
    {
        return $this->morphMany(ActiveProductAttributes::class, 'activable');
    }
    public function activeProductCategories()
    {
        if ($this->activeProductAttributes()->count() > 0)
            return $this->activeProductAttributes()->whereNull('collection_code')->whereNull('colour_code');
    }
    public function activeProductCollections()
    {
        if ($this->activeProductAttributes()->count() > 0)
            return $this->activeProductAttributes()->whereNotNull('category_code')->whereNotNull('collection_code')->whereNull('colour_code');
    }
    public function activeProductColours()
    {
        if ($this->activeProductAttributes()->count() > 0)
            return $this->activeProductAttributes()->whereNotNull('category_code')->whereNotNull('collection_code')->whereNotNull('colour_code');
    }
    public function overdue_amounts()
    {
        return $this->morphMany(OverdueAmount::class, 'overdueable');
    }
    public function customers()
    {
        return $this->morphMany(Customer::class, 'customerable');
    }
    public function state()
    {
        return $this->belongsTo(State::class, 'state_code');
    }
    public function stock_points()
    {
        return $this->morphMany(CustomerStockPoint::class, 'customerable')->orderBy('sort_order'); //#808 added order by for stock point sorting on UI
    }
    public function blanketOrders()
    {
        return $this->morphMany(BlanketOrder::class, 'orderable');
    }
    public function advanceBlanketOrders()
    {
        return $this->morphMany(AdvanceBlanketOrder::class, 'orderable');
    }
    public function cl_rates()
    {
        return $this->morphMany(CLRate::class, 'CLable');
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function sales_manager()
    {
        return $this->belongsTo(SalesManager::class);
    }
}
