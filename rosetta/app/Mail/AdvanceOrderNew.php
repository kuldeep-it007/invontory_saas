<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceBlanketOrder;

class AdvanceOrderNew extends Mailable
{
    use Queueable, SerializesModels;
    public $blanketOrder;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceBlanketOrder $blanketOrder)
    {
        $this->blanketOrder = $blanketOrder;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.advanceOrderNew');
    }
}
