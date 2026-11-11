<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Message;
use Rosetta\Transformers\Message\MessagesTransformer;

class AdvanceItemMessagesTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['messages'];
	protected $defaultIncludes = ['messages'];
	public function transform(AdvanceItem $item)
	{
		return [
			"id" => $item->id
		];
	}
	public function includeMessages(AdvanceItem $item)
	{
		return $this->collection($item->messages,new MessagesTransformer);
	}
}