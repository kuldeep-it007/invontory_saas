<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\User;

class AccountCreated extends Mailable
{
    use Queueable, SerializesModels;
    public $dealer, $distributor, $email, $password;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Dealer $dealer, Distributor $distributor, $email, $password)
    {
        $this->dealer = $dealer;
        $this->distributor = $distributor;
        $this->email = $email;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.dealers.accountCreated');
    }
}
