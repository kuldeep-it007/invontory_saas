<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Message;
use Rosetta\Models\User;

class AdvanceCartItemMessage extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceItem, $message, $user, $adminUser, $advanceItemProductName, $region, $company, $customerType, $customer;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceItem $advanceItem, Message $message, User $user, $adminUser, $advanceItemProductName, $region, $company, $customerType, $customer)
    {
        $this->advanceItem = $advanceItem;
        $this->message = $message;
        $this->user = $user;
        $this->adminUser = $adminUser;
        $this->advanceItemProductName = $advanceItemProductName;
        $this->region = $region;
        $this->company = $company;
        $this->customerType = $customerType;
        $this->customer = $customer;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.request');
    }
}
