<?php

namespace Rosetta\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Rosetta\Channels\Messages\SMSMessage;
use Rosetta\Notifications\SMSNotification;

class OTPNotification extends SMSNotification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function toSMS($notifiable)
    {
        return (new SMSMessage)
            ->to($notifiable->number)
            ->message("Please use OTP {$notifiable->otp} to login to " . config('brand.brand'));
    }
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */

}
