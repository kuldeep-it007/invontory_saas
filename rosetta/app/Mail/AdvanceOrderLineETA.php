<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceOrder\AdvanceLine;

class AdvanceOrderLineETA extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceLine, $date, $advanceItemProductName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceLine $advanceLine, $date, $advanceItemProductName)
    {
        $this->advanceLine = $advanceLine;
        $this->date = $date;
        $this->advanceItemProductName = $advanceItemProductName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.LineETA');
    }
}
