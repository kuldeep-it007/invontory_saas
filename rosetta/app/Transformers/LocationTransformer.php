<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
class LocationTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['taxTypes'];
	protected $defaultIncludes = ['taxTypes'];

	public function transform(Location $location)
	{
		return [
			"id" => $region->id,
			"name" => $region->name,
			"created_at" => $region->created_at->format('U'),
			"updated_at" => $region->updated_at->format('U'),
		];
	}
	public function includeTaxTypes(Region $region)
	{
		return $this->collection($region->taxTypes,new TaxTypeTransformer);
	}
}