<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Region;
class CategoryTransformer extends TransformerAbstract
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
	public function transform(Category $category)
	{
		$in_multiples = $category->in_multiples;
		$plank_size = $category->plank_size;//#641

		if($this->region && $this->sales_unit_of_measure)
		{
			$uom = $this->region->getUOM('sales_unit_of_measure',$this->sales_unit_of_measure);
			if($uom)
			{
				$in_multiples*=$uom->conversion_rate;
				$plank_size*=$uom->conversion_rate;
			}
		}
		$display_name = $category->code;
		if($this->user_type != null)
		{
			$query = //$this->user_type->activeProductCategories()->
			ActiveProductAttributes::where('category_code', '=', $category->code);
            if($this->withTrashed)
            	$query->withTrashed();
            $activeCategory = $query->first();

			if($activeCategory)
				$display_name = $activeCategory->category_display;
		}
		return [
			"code" => $category->code,
			"display_name" => $display_name,
			"description" => $category->description,
			"allow_cl" => (bool)$category->allow_cl,
			"cl_percent" => (float)$category->cl_percent,
			"in_multiples" => round((float)$in_multiples,2),
			"plank_size" => round((float)$plank_size,2),
		];
	}
}