<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MUPSet extends Mailable
{
    use Queueable, SerializesModels;
    public $product, $price;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($product, $price)
    {
        $this->product = $product;
        $this->price = $price;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.product.priceSet');
    }
}
