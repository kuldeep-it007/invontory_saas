<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\ActiveProductAttributes;

class ActiveProductAttributesTransformer extends TransformerAbstract
{
	public function transform(ActiveProductAttributes $activeProductAttributes)
	{
		return [
			"id" => $activeProductAttributes->id,
			"category_code" => $activeProductAttributes->category_code,
			"category_display" => $activeProductAttributes->category_display,
			"collection_code" => $activeProductAttributes->collection_code,
			"collection_display" => $activeProductAttributes->collection_display,
			"colour_code" => $activeProductAttributes->colour_code,
			"colour_display" => $activeProductAttributes->colour_display,
		];
	}
}