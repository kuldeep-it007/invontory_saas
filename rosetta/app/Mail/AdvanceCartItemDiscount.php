<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceOrder\AdvanceItem;

class AdvanceCartItemDiscount extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceItem, $currentDiscount, $advanceItemProductName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceItem $advanceItem, $currentDiscount, $advanceItemProductName)
    {
        $this->advanceItem = $advanceItem;
        $this->currentDiscount = $currentDiscount;
        $this->advanceItemProductName = $advanceItemProductName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.Discount');
    }
}
