<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\Message;
use Rosetta\Transformers\Message\MessagesTransformer;

class AdvanceLineMessagesTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['messages'];
	protected $defaultIncludes = ['messages'];
	public function transform(AdvanceLine $line)
	{
		return [
			"id" => $line->id
		];
	}
	public function includeMessages(AdvanceLine $line)
	{
		return $this->collection($line->messages,new MessagesTransformer);
	}
}