<?php
namespace Rosetta\Transformers\ProductPrice;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Product;

class PriceTransformer extends TransformerAbstract
{
	protected $company;
	public function __construct(Company $company)
	{
		$this->company = $company;
	}
	public function transform(Product $product)
	{
		$price = $product->prices()->where('company_id','=',$this->company->id)->first();
		
		$out = [
			"unit" => $price? $price->price : 0,
		];
		return $out;
	}
}