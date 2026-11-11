<?php
namespace Rosetta\Transformers\ReturnItem;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Product;
use Rosetta\Models\ReturnItem;
use Rosetta\Models\User;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Product\ItemCartProductTransformer;


class ReturnItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product', 'customer'];
	protected $availableIncludes = ['category','collection','colour'];
	public function transform(ReturnItem $returnItem)
	{
		$data = [
			"id" => $returnItem->id,
			"company_id" => $returnItem->company_id,
			"distributor_id" => $returnItem->distributor_id,
			"product_id" => $returnItem->product_id,
			"batch_no" => $returnItem->batch_no,
			"quantity" => $returnItem->quantity,
			"rate_type" => $returnItem->rate_type,
			"unit_price" => $returnItem->unit_price,
			"discount" => $returnItem->discount,
			"amount" => $returnItem->amount,
			"status" => $returnItem->status,
		//	"authorised_by" => $returnItem->authorised_by,
			"authorisation_date" => $returnItem->authorisation_date ? $returnItem->authorisation_date->format('U') : null,
			"reject_reason" => $returnItem->reject_reason,
			"created_at" => $returnItem->created_at->format('U'),
			"purchase_order_number" => $returnItem->returnable->purchase_order->order_number,
		];
		if($returnItem->authorised_by)
		{
			$authorised_by = User::find($returnItem->authorised_by);
			$data['authorised_by'] = $authorised_by->email;
		}
		return $data;
	}
	public function includeProduct(ReturnItem $returnItem)
	{
	//	var_dump($returnItem->returnable->purchase_order->company->region);
		$region = $returnItem->returnable->purchase_order->company->region;
		$customer = $returnItem->dealer;
		return $this->item($returnItem->product, new ItemCartProductTransformer($region, $customer));
	}
	public function includeCustomer(ReturnItem $returnItem)
	{
		if(!$returnItem->customer_id)
		{
			return null;
		}
		if($returnItem->customer_type == 'DEALER')
		{
			return $this->item($returnItem->dealer,new DealerTransformer);
		}
	}
}