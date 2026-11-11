<?php
namespace Rosetta\Transformers\SalesManager;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\SalesManager;
class SalesManagerTransformer extends TransformerAbstract
{
	public function transform(SalesManager $salesmanager)
	{
		return [
			"id" => $salesmanager->id,
			"name" => $salesmanager->name
		];
	}
}