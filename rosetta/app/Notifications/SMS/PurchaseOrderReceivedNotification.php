<?php

namespace Rosetta\Notifications\SMS;

use Rosetta\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Rosetta\Channels\Messages\SMSMessage;
use Rosetta\Notifications\SMSNotification;

class PurchaseOrderReceivedNotification extends SMSNotification
{
    use Queueable;

    /**
     * 
     *
     * @var \Rosetta\Models\Company
     */
    protected $company;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Company $company)
    {
        parent::__construct();
        $this->company = $company;
        
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toSMS($notifiable)
    {
        $brand =config('brand.brand');
        return (new SMSMessage)
            ->to($notifiable->number)
            ->company($this->company)
            ->message("Dear {$notifiable->supplier}, you have received an order from {$notifiable->customer}. Thank you! 
 -Team {$brand}");
          //   ->message("Your order No. {$notifiable->supplier}, has been placed with {$notifiable->customer}. Thank you! 
 //-Team {$brand}");
    }
    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
