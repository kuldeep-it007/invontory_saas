<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\TempDealer;

class DealerRejected extends Mailable
{
    use Queueable, SerializesModels;
    public $dealer;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(TempDealer $dealer)
    {
        $this->dealer = $dealer;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.dealers.dealerRejected');
    }
}
