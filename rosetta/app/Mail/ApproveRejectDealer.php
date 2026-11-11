<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\Distributor;
use Rosetta\Models\TempDealer;

class ApproveRejectDealer extends Mailable
{
    use Queueable, SerializesModels;
    public $tempDealer, $distributor;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(TempDealer $tempDealer, Distributor $distributor)
    {
        $this->tempDealer = $tempDealer;
        $this->distributor = $distributor;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.dealers.approveRejectDealer');
    }
}
