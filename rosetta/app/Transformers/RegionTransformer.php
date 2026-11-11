<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Region;
use Rosetta\Models\TaxType;
use Rosetta\Transformers\Location\CountryTransformer;
use Rosetta\Transformers\TaxTypeTransformer;
class RegionTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['taxTypes', 'country'];
	protected $defaultIncludes = ['taxTypes', 'country'];

	public function transform(Region $region)
	{
		return [
			"id" => $region->id,
			"name" => $region->name,
			"tax_information" => $region->tax_information? explode(',',$region->tax_information) : [],
			"created_at" => $region->created_at->format('U'),
			"updated_at" => $region->updated_at->format('U'),
		];
	}
	public function includeTaxTypes(Region $region)
	{
		return $this->collection($region->taxTypes,new TaxTypeTransformer);
	}
	public function includeCountry(Region $region)
	{
		return $this->item($region->country, new CountryTransformer);
	}
}