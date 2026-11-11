<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\Distributor;
use Rosetta\Models\User;

class DealersCreated extends Mailable
{
    use Queueable, SerializesModels;
    public $distributor, $UserList;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Distributor $distributor, $UserList)
    {
        $this->distributor = $distributor;
        $this->UserList = $UserList;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.dealers.dealersCreated');
    }
}
