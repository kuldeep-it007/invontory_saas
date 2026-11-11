<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceOrder\AdvanceItem;

class AdvanceCartItemQuantity extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceItem, $currentQuantity, $advanceItemProductName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceItem $advanceItem, $currentQuantity, $advanceItemProductName)
    {
        $this->advanceItem = $advanceItem;
        $this->currentQuantity = $currentQuantity;
        $this->advanceItemProductName = $advanceItemProductName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.Quantity');
    }
}
