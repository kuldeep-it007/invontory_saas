<?php

namespace Rosetta\Mail\SuperStockist;

use Rosetta\Models\User;
use Rosetta\Models\Dealer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Rosetta\Models\Distributor;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Rosetta\Models\SuperStockist\SuperStockist;

class AccountCreated extends Mailable
{
    use Queueable, SerializesModels;
    public $superStockist, $distributor, $email, $password;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Distributor $distributor,SuperStockist $superStockist, $email, $password)
    {
        $this->distributor = $distributor;
        $this->superStockist = $superStockist;
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
        return $this->markdown('emails.superStockist.accountCreated');
    }
}
