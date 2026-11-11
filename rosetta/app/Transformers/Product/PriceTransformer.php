<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Product;
use Rosetta\Services\Product\ProductsServiceInterface;
use Rosetta\PaymentType;
class PriceTransformer extends TransformerAbstract
{
	/**
	 * @var \Rosetta\Models\Company
	 */
	protected $company;
	protected $customer;
	protected $customerType;
	/**
	 * @var \Rosetta\Services\CLRate\CLRateServiceInterface
	 */
	protected $clRateService;
	/**
	 * @var \Rosetta\Services\Discount\DiscountsServiceInterface
	 */
	protected $discountService;
	/**
	 * @var \Rosetta\Services\OnlineDiscounts\OnlineDiscountsServiceInterface
	 */
	protected $onlineDiscountsService;
	public function __construct(Company $company,$customer = null, $customerType = null)
	{
		$this->customer = $customer;
		$this->customerType = $customerType;
		$this->company = $company;
		$this->discountService = resolve('Rosetta\Services\Discount\DiscountsServiceInterface');
		$this->clRateService = resolve('Rosetta\Services\CLRate\CLRateServiceInterface');
		$this->onlineDiscountsService = resolve('Rosetta\Services\OnlineDiscounts\OnlineDiscountsServiceInterface');
	}
	public function transform(Product $product)
	{
		$discount = 0;
		$onlineDiscount = 0;
		$price = $product->prices()->where('company_id','=',$this->company->id)->first();
		if($this->customerType)
			$discount = round($this->discountService->getDiscount($this->customerType,$this->customer,$product),2);
		if($this->customerType)
		{
			if(($this->customer->payment_type & PaymentType\ONLINE) == PaymentType\ONLINE)
			{
				$onlineDiscount = round($this->onlineDiscountsService->getDiscount($this->company,$product));
			}
		}
		$out = [
			"unit"           => $price? $price->price : 0,
			"discount"       => $discount,
			"onlineDiscount" => $onlineDiscount
		];

		if($product->category->allow_cl)
		{
			$clPercent = $this->clRateService->getRate($this->company,$product,$this->customerType,$this->customer->id);
			$out["allow_cl"] = true;
			$out["cl_percent"] = $clPercent;
		}
		$tax = $this->company->region->taxes()->where('category_code','=',$product->category->code)->where('collection_code','=',$product->collection->code)->first();
		if(!$tax)
		{
			$tax = $this->company->region->taxes()->where('category_code','=',$product->category->code)->first();
		}
		if($tax)
		{
			$out["tax_percent"] = $tax->tax_percent;
			$out["tax_type_code"] = $tax->tax_type_code;
		}
		return $out;
	}
}