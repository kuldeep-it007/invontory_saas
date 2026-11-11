<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\PurchaseOrder\PurchaseLine;

class PurchaseLineUpdateCoDistributor extends Mailable
{
    use Queueable, SerializesModels;
    public $purchaseLine, $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(PurchaseLine $purchaseLine, $data)
    {
        $this->purchaseLine = $purchaseLine;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.purchaseOrders.purchaseLineUpdateCoDistributor');
    }
}
