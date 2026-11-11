<?php
namespace Rosetta\Transformers\Product;
use stdClass;
use League\Fractal\TransformerAbstract;
class NewProductTransformer extends TransformerAbstract
{
	public function transform(stdClass $result)
	{
		$out = [];
		if($result->product)
		{
			$out['product']  = [
				'id' => $result->product->id,
				"nav_id" => $result->product->nav_id,
			];
		}
		else
		{
			$out['product'] = null;
		}
		$out['companies'] = [];
		foreach($result->companies as $companyRec)
		{

			$companyResult = [
				"company" => [
					"id" => $companyRec->company->id,
					"name" => $companyRec->company->name,
				],
				"item_no" => null,
				"error" => null,
			];
			if($companyRec->itemCard)
			{
				$companyResult['item_no'] = $companyRec->itemCard->getNo();
			}
			else
			{
				$companyResult["error"]  = $companyRec->error;
			}
			$out['companies'][] = $companyResult;
		}
		$out['cms'] = $result->cms;
		return $out;
	}
}