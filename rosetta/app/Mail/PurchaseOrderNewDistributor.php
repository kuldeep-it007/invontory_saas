<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\BlanketOrder;

class PurchaseOrderNewDistributor extends Mailable
{
    use Queueable, SerializesModels;
    public $blanketOrder;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(BlanketOrder $blanketOrder)
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
        return $this->markdown('emails.purchaseOrders.purchaseOrderNewDistributor');
    }
}
