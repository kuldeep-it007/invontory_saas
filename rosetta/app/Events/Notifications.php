<?php

namespace Rosetta\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class Notifications implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    protected $queuePrefix;
    protected $notificationObject;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($notification, $blanketOrder=null)
    {        
        //$this->notification = $notification;
        $this->notificationObject = $notification;
        $this->notification = [
            "id" => $this->notificationObject->id,
            "title" => $this->notificationObject->title,
            "text" => $this->notificationObject->text,
            "created_at" => $this->notificationObject->created_at->format('U'),
            "sender" => [
                "id" => $this->notificationObject->sender->id,
                "name" => $this->notificationObject->sender->name,
            ],
            "recipient" => [
                "id" => $this->notificationObject->recipient->id,
            ]
        ];
        $this->queuePrefix = config('brand.queue_prefix');
        $this->queue =  $this->queuePrefix.'.notifications';
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
       return new PrivateChannel("{$this->queuePrefix}-user-{$this->notificationObject->recipient->id}"); 
        //return new PrivateChannel("{$this->queuePrefix}-user-10"); 
    }  
    public function broadcastAs()
    {
        return 'notification';
    }

}