<?php
namespace Rosetta\Transformers\ProductTax;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Tax;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\TaxTypeTransformer;
class ProductTaxTransformer extends TransformerAbstract
{
//	protected $availableIncludes = ['category', 'tax_type'];
//	protected $defaultIncludes = ['category','tax_type'];
	public function transform(Tax $tax)
	{
		return [
			"id" => $tax->id,
			"region_id" => $tax->region_id,
			"category_code" => $tax->category_code,
			"collection_code" => $tax->collection_code,
			"tax_type_code" => $tax->tax_type_code,
			"tax_percent" => $tax->tax_percent
		];
	}
/*	public function includeCategory(Tax $tax)
	{
		if(!$tax->category)
			return null;
		return $this->item($tax->category,new CategoryTransformer);
	}
	public function includeTaxType(Tax $tax)
	{
		if(!$tax->taxType)
			return null;
		return $this->item($tax->taxType,new TaxTypeTransformer);
	}*/
}