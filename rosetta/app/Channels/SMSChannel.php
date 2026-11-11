<?php

namespace Rosetta\Channels;

use Illuminate\Notifications\Notification;
class SMSChannel
{
	public function send($notifiable, Notification $notification)
    {
        /**
         * @var \Rosetta\Channels\Messages\SMSMessage;
         */
        $message = $notification->toSMS($notifiable);
        $s = resolve('Rosetta\Services\Notifications\SMS\SMSGatewayServiceInterface');
        foreach($message->getTo() as $to)
        {
            $s->send($message->getCompany(),$to,$message->getMessage());
        }
    }
}