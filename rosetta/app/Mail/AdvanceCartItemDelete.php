<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvanceCartItemDelete extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceItemProductName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($advanceItemProductName)
    {
        $this->advanceItemProductName = $advanceItemProductName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.DeleteItem');
    }
}
