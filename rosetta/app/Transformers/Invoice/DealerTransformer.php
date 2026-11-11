<?php 
namespace Rosetta\Transformers\Invoice;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;

class DealerTransformer extends TransformerAbstract
{
	public function transform(Dealer $dealer)
	{

		$data = [
			"id" => $dealer->id,
			"name" => $dealer->name,
		];
		return $data;
	}
}
