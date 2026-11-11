<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Region;

class ColourTransformer extends TransformerAbstract
{
	protected $region, $withTrashed;
	protected $sales_unit_of_measure, $user_type, $category_code;
	public function __construct(Region $region = null,$sales_unit_of_measure = null, $user_type = null, $category_code = null, $withTrashed = false)
	{
		$this->region = $region;
		$this->sales_unit_of_measure = $sales_unit_of_measure;
		$this->user_type = $user_type;
		$this->category_code = $category_code;
		$this->withTrashed = $withTrashed;
	}

	public function transform(Colour $colour)
	{
		$in_multiples = $colour->in_multiples;
		$plank_size = $colour->plank_size;//#641

		if($this->region && $this->sales_unit_of_measure)
		{
			$uom = $this->region->getUOM('sales_unit_of_measure',$this->sales_unit_of_measure);
			if($uom)
			{
				$in_multiples*=$uom->conversion_rate;
				$plank_size*=$uom->conversion_rate;//#641
			}
		}
		$display_name = $colour->code;
		if($this->user_type != null)
		{
            $query = //$this->user_type->activeProductColours()->
			ActiveProductAttributes::where('category_code', '=', $this->category_code)
                ->where('collection_code', '=', $colour->collection_code);

            if($this->withTrashed)
            	$query->withTrashed();

            $colourCnt = $query->count();
            if($colourCnt > 0)
            {
				$query = //$this->user_type->activeProductColours()->
				ActiveProductAttributes::where('category_code', '=', $this->category_code)
											->where('collection_code', '=', $colour->collection_code)
											->where('colour_code', '=', $colour->code);
				if($this->withTrashed)
            		$query->withTrashed();

            	$activeColour = $query->first();
				if($activeColour)
					$display_name = $activeColour->colour_display;
            }
            else
            {
            	$user_company = $this->user_type->company;
				if($user_company)
				{
					$query = //$user_company->activeProductColours()->
					ActiveProductAttributes::where('category_code', '=', $this->category_code)
												->where('collection_code', '=', $colour->collection_code)
												->where('colour_code', '=', $colour->code);
					if($this->withTrashed)
            			$query->withTrashed();

            		$activeColour = $query->first();
					if($activeColour)
						$display_name = $activeColour->colour_display;
				}
            }
		}
		return [
			"code" => $colour->code,
			"display_name" => $display_name,
			"description" => $colour->description,
			"in_multiples" => round((float)$in_multiples,2),
			"plank_size" => round((float)$plank_size,2),
		];
	}
}