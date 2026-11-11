<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceOrder\AdvanceLine;

class AdvanceLineUpdateDud extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceLine, $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceLine $advanceLine, $data)
    {
        $this->advanceLine = $advanceLine;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.advanceLineUpdateDud');
    }
}
