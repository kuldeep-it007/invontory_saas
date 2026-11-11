<?php
namespace Rosetta\Transformers\Notification;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Notification;
use Rosetta\Transformers\AuthorTransformer;

class NotificationsTransformer extends TransformerAbstract
{
//	protected $availableIncludes = ['author'];
//	protected $defaultIncludes = ['author'];
	public function transform(Notification $notification)
	{
		return [
			"id" => $notification->id,
			"title" => $notification->title,
			"text" => $notification->text,
			"created_at" => $notification->created_at->format('U'),
			"sender" => [
                "id" => $notification->sender->id??'',
                "name" => $notification->sender->name,
            ],
            "recipient" => [
                "id" => $notification->recipient->id,
			],
			"read" => $notification->read,
		];
	}
/*	public function includeAuthor(Notification $notification)
	{
		return $this->item($notification->author,new AuthorTransformer);
	}*/
}