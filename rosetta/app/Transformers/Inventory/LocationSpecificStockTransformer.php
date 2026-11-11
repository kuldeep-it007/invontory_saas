<?php
namespace Rosetta\Transformers\Inventory;

use League\Fractal\TransformerAbstract;
use stdClass;
class LocationSpecificStockTransformer extends TransformerAbstract
{
	public function transform(stdClass $item)
	{
		return [
			"id" => $item->id,
			"source" => $item->type,
			"name" => (!empty($item->display_name))?$item->display_name:$item->name,
			"display_name" => (!empty($item->display_name))?$item->display_name:$item->name,
            "inventory" => $item->inventory,
            "blocked" => $item->blocked,
            "intransit" => $item->intransit,
            "intransit_blocked" => $item->intransit_blocked,
		];
	}
}