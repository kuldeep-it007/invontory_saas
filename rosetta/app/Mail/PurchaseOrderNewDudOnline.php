<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\BlanketOrder;

class PurchaseOrderNewDudOnline extends Mailable
{
    use Queueable, SerializesModels;
    public $blanketOrder, $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(BlanketOrder $blanketOrder, $data)
    {
        $this->blanketOrder = $blanketOrder;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.purchaseOrders.purchaseOrderNewDudOnline');
    }
}
