<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\MasterData;

class MasterDataTransformer extends TransformerAbstract
{
	public function transform(MasterData $masterData)
	{
		return [
			"type" => $masterData->type,
			"key" => $masterData->key,
			"value" => $masterData->value,
			"sort" => $masterData->sort
		];
	}
}