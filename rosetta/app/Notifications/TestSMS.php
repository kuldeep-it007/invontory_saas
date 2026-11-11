<?php

namespace Rosetta\Notifications;

use Rosetta\Channels\Messages\SMSMessage;
use Rosetta\Notifications\SMSNotification;

class TestSMS extends SMSNotification
{
    public function toSMS($notifiable)
    {
        return (new SMSMessage)
            ->to(9741999114)
            ->message("test message from Rosetta");
        /*return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
                    */
    }
}
