<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\Distributor;
use Illuminate\Mail\Mailables\Attachment;

class SalesReport extends Mailable
{
    use Queueable, SerializesModels;
    public $distributor, $attachment, $prev_mon_1, $prev_yr_1, $subject;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Distributor $distributor, $prev_mon_1, $prev_yr_1, $subject, string $attachment)
    {
        $this->distributor = $distributor;
        $this->attachment = $attachment;
        $this->prev_mon_1 = $prev_mon_1;
        $this->prev_yr_1 = $prev_yr_1;
        $this->subject = $subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        //return $this->markdown('emails.reports.dealerSales');
        return $this->view('emails.reports.distributorSales')
                ->subject($this->subject)
                ->attach($this->attachment);
    }
   /* public function attachments(): array
    {
        return [
            Attachment::fromPath($this->attachment),
        ];
    }*/
}
