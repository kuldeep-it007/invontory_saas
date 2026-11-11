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
use Rosetta\Models\Dealer;
use Rosetta\Models\Engineer;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\State;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Target;
use Rosetta\NAV\WS\Customer\Customer_Card;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Traits\Author;
use Rosetta\Traits\TransactionTrait;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class Distributor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    use Author;
    use TransactionTrait;
    use HasRoles; //#807
    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    protected $customerService;
    protected $customerCard;
    protected $guard_name = 'web'; //#807
    protected $fillable = [
        'id',
        'nav_id',
        'name',
        'contact',
        'phone',
        'email',
        'gstin',
        'company_id',
        'super_stockist_id',
        'to_email',
        'cc_email',
        'payment_type',
        'invoice_series',
        'overdue_balance',
        'overdue_hold_days',
        'credit_limit',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'address',
        'address_2',
        'city_code',
        'state_code',
        'post_code',
        'is_moved_from_pd',
        'is_moved_from_md'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function dealers()
    {
        return $this->hasMany(Dealer::class);
    }
    public function superStockist() //#894
    {
        return $this->belongsTo(SuperStockist::class);
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
        return $this->morphMany(CartItem::class, 'itemable');
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
    public function state()
    {
        return $this->belongsTo(State::class, 'state_code');
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
    public function engineers()
    {
        return $this->morphMany(Engineer::class, 'engineerable');
    }
    public function stock_points()
    {
        return $this->morphMany(CustomerStockPoint::class, 'customerable')->orderBy('sort_order'); //#808 added order by for stock point sorting on UI
    }
    public function blanketOrders()
    {
        return $this->morphMany(BlanketOrder::class, 'orderable');
        //->whereNull('distributor_id');//CO-Dist Orders have distributor_id
    }
    public function advanceBlanketOrders()
    {
        return $this->morphMany(AdvanceBlanketOrder::class, 'orderable')
            ->whereNull('distributor_id');
    }
    public function coDistributors() //co distributors with whom I can place POs
    {
        return $this->belongsToMany(Distributor::class, 'co_distributors', 'distributor_id', 'co_distributor_id')->withPivot('overdue_hold_days', 'overdue_balance', 'credit_limit', 'id');
    }
    public function parentCoDistributors() //co distributors who can place SOs with me
    {
        return $this->belongsToMany(Distributor::class, 'co_distributors', 'co_distributor_id', 'distributor_id')->withPivot('overdue_hold_days', 'overdue_balance', 'credit_limit', 'id');
    }
    public function coDistributorBlanketOrders() //gives Purchase Orders I placed with my Co Distributors
    {
        return $this->morphMany(BlanketOrder::class, 'orderable')
            ->whereNotNull('distributor_id');
    }

    public function parentCoDistributorBlanketOrders() //gives Sales Orders placed with me by my Co Distributors
    {
        return $this->hasMany(BlanketOrder::class)
            ->where('orderable_type', '=', Distributor::class);
    }
    public function cl_rates()
    {
        return $this->morphMany(CLRate::class, 'CLable');
    }
    public function targets()
    {
        return $this->morphMany(Target::class, 'targetable');
    }
}
