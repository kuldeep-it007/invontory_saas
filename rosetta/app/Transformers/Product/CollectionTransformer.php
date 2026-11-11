<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Region;

class CollectionTransformer extends TransformerAbstract
{

	protected $region, $withTrashed;
	protected $sales_unit_of_measure, $user_type;
	public function __construct(Region $region = null,$sales_unit_of_measure = null, $user_type = null, $withTrashed = false)
	{
		$this->region = $region;
		$this->sales_unit_of_measure = $sales_unit_of_measure;
		$this->user_type = $user_type;
		$this->withTrashed = $withTrashed;
	}
	public function transform(Collection $collection)
	{
		$in_multiples = $collection->in_multiples;
		$plank_size = $collection->plank_size;//#641

		if($this->region && $this->sales_unit_of_measure)
		{
			$uom = $this->region->getUOM('sales_unit_of_measure',$this->sales_unit_of_measure);
			if($uom)
			{
				$in_multiples*=$uom->conversion_rate;
				$plank_size*=$uom->conversion_rate;
			}
		}
		$display_name = $collection->code;
		if($this->user_type != null)
		{							
			$query = //$this->user_type->activeProductCollections()->
			ActiveProductAttributes::where('category_code', '=', $collection->category_code)
									->where('collection_code', '=', $collection->code);

            if($this->withTrashed)
            	$query->withTrashed();

            $activeCollection = $query->first();
			if($activeCollection)
				$display_name = $activeCollection->collection_display;
		}
		return [
			"code" => $collection->code,
			"display_name" => $display_name,
			"description" => $collection->description,
			"in_multiples" => round((float)$in_multiples,2),
			"category_code" => $collection->category_code,
			"plank_size" => round((float)$plank_size,2),
		];
	}
}