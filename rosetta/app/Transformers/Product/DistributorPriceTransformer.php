<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Product;
use Rosetta\Services\Product\ProductsServiceInterface;

class DistributorPriceTransformer extends TransformerAbstract
{
	protected $customer;
	protected $customerType;
	protected $productsService;
	protected $customerDiscountService;
	protected $clRateService;
	protected $discountService;

	public function __construct($customer = null, $customerType = null)
	{
		$this->customer = $customer;
		$this->customerType = $customerType;
		$this->customerDiscountService = resolve('Rosetta\Services\CustomerDiscount\CustomerDiscountServiceInterface');
		$this->clRateService = resolve('Rosetta\Services\CLRate\CLRateServiceInterface');
		$this->discountService = resolve('Rosetta\Services\Discount\DiscountsServiceInterface');
	}
	public function transform(Product $product)
	{
		$customer_discount = 0;
		$price = $product->prices()->where('company_id','=',$this->customer->company_id)->first();
		if($this->customerType)
			$customer_discount = round($this->customerDiscountService->getCustomerDiscount($this->customerType,$this->customer,$product, $this->customer->distributor_id),2);
		if($customer_discount == 0)
		{
			if($this->customerType == 'DEALER')
			{
				$customer_discount = round($this->discountService->getDiscount($this->customerType,$this->customer,$product),2);
			}
		}//#803
		/*$company_price = $product->prices()->where('company_id','=',$this->customer->company_id)->first()->price;//company price
		$customer_price = $company_price - ($company_price * ($customer_discount/100));*/
		$out = [
			"unit" => $price? $price->price : 0,
			"discount" => $customer_discount,	
		];
		if($product->category->allow_cl)
		{
			$clPercent = $this->clRateService->getRate($this->customer->distributor,$product,$this->customerType,$this->customer->id);
			if($clPercent == 0 && $this->customer->distributor->super_stockist == ""){
				$clPercent = $this->clRateService->getRate($this->customer->distributor->company,$product,$this->customerType,$this->customer->id);
			}
			$out["allow_cl"] = true;
			$out["cl_percent"] = $clPercent;
		}
		$tax = $this->customer->company->region->taxes()->where('category_code','=',$product->category->code)->where('collection_code','=',$product->collection->code)->first();
		if(!$tax)
		{
			$tax = $this->customer->company->region->taxes()->where('category_code','=',$product->category->code)->first();
		}
		if($tax)
		{
			$out["tax_percent"] = $tax->tax_percent;
			$out["tax_type_code"] = $tax->tax_type_code;
		}
		return $out;
	}
}