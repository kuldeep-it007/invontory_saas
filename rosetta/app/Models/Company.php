<?php

namespace Rosetta\Models;

use Rosetta\Models\CLRate;
use Rosetta\Models\Dealer;
use Rosetta\Models\Region;
use Rosetta\Models\Target;
use Rosetta\Traits\Author;
use Rosetta\Models\Distributor;
use Rosetta\Models\Transaction;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Product\Price;
use Rosetta\Traits\SettingsTrait;
use Rosetta\Models\OnlineDiscount;
use Rosetta\Models\RetailCustomer;
use Rosetta\Models\Cart\BlockedItem;
use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\AdvanceBlanketOrder;
use Illuminate\Database\Eloquent\Builder;
use Rosetta\Models\Company\BillingLocation;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\Company\CustomerStockPoint;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\AdvanceOrder\BlockedAdvanceItem;
use Rosetta\Models\Product\ActiveProductAttributes;


class Company extends Model
{
    use Author;
    use SettingsTrait;
    protected $dateFormat = 'U';
    public function region()
    {
    	return $this->belongsTo(Region::class);
    }
    public function distributors()
    {
    	return $this->hasMany(Distributor::class);
    }
    public function super_stockists()
    {
        return $this->hasMany(SuperStockist::class);
    }
    public function dealers()
    {
        return $this->hasMany(Dealer::class);
    }
    public function distributorDealers()
    {
        return $this->dealers()->whereNotNull('distributor_id');
    }
    public function companyDealers()
    {
        return $this->dealers()->whereNull('distributor_id');
    }
    public function tempDealers()
    {
        return $this->hasMany(TempDealer::class);
    }
    public function billing_locations()
    {
        return $this->hasMany(BillingLocation::class);
    }
    public function productPrices()
    {
        return $this->hasMany(Price::class);
    }
    public function blockedItems()
    {
        return $this->hasMany(BlockedItem::class);
    }
    public function blockedAdvanceItems()
    {
        return $this->morphMany(BlockedAdvanceItem::class, 'blockable');
    }
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    public function advanceOrders()
    {
        return $this->hasMany(AdvanceOrder::class);
    }
    public function advanceItems()
    {
        return $this->hasMany(AdvanceItem::class);
    }
    public function retailCustomers()
    {
        return $this->hasMany(RetailCustomer::class);
    }
    public function activeProductAttributes()
    {
        return $this->morphMany(ActiveProductAttributes::class, 'activable');
    }
    public function activeProductCategories()
    {
        return $this->activeProductAttributes()->whereNull('collection_code')->whereNull('colour_code');
    }
    public function activeProductCollections()
    {
        return $this->activeProductAttributes()->whereNotNull('category_code')->whereNotNull('collection_code')->whereNull('colour_code');
    }
    public function activeProductColours()
    {
        return $this->activeProductAttributes()->whereNotNull('category_code')->whereNotNull('collection_code')->whereNotNull('colour_code');
    }
    public function engineers()
    {
        return $this->morphMany(Engineer::class, 'engineerable');
    }
    public function stock_points()
    {
        return $this->morphMany(CustomerStockPoint::class, 'customerable')->orderBy('sort_order');//#808 added order by for stock point sorting on UI
    }
    public function blanketOrders()
    {
        /*return $this->hasMany(BlanketOrder::class)->whereNull('distributor_id')->whereNull('super_stockist_id')->whereHasMorph('orderable','*',function(Builder $query,$type){
            if($type == Distributor::class)
            {
                $query->whereNull('super_stockist_id');
            }
        });//added last whereNull to accomodate #910
        */
        #Track-0001 Start
        return $this->hasMany(BlanketOrder::class)->with('purchase_orders', 'purchase_orders.orderable')->whereHas('purchase_orders', function ($q) { 
            $q->whereNull('distributor_id')->whereNull('super_stockist_id'); 
        });//added last whereNull to accomodate #910
        #Track-0001 END
    }
    public function cl_rates()
    {
        return $this->morphMany(CLRate::class, 'CLable');
    }
    public function onlineDiscounts()
    {
        return $this->morphMany(OnlineDiscount::class, 'discountable');
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function advanceBlanketOrders()
    {
        return $this->hasMany(AdvanceBlanketOrder::class)->whereNull('distributor_id')->whereNull('super_stockist_id');
    }   
    public function targets()
    {
        return $this->morphMany(Target::class, 'targetable');
    }
}