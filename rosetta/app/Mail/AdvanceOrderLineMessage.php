<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\Message;
use Rosetta\Models\User;

class AdvanceOrderLineMessage extends Mailable
{
    use Queueable, SerializesModels;
    public $advanceLine, $message, $user, $adminUser, $advanceItemProductName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AdvanceLine $advanceLine, Message $message, User $user, $adminUser, $advanceItemProductName)
    {
        $this->advanceLine = $advanceLine;
        $this->message = $message;
        $this->user = $user;
        $this->adminUser = $adminUser;
        $this->advanceItemProductName = $advanceItemProductName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.advanceOrders.LineMessage');
    }
}
