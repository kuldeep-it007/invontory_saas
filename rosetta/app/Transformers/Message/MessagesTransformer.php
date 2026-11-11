<?php
namespace Rosetta\Transformers\Message;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Message;
use Rosetta\Transformers\AuthorTransformer;

class MessagesTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['author'];
	protected $defaultIncludes = ['author'];
	public function transform(Message $message)
	{
		return [
			"text" => $message->text,
			"created_at" => $message->created_at->format('U'),
		];
	}
	public function includeAuthor(Message $message)
	{
		return $this->item($message->author,new AuthorTransformer);
	}
}