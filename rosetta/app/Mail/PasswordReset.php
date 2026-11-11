<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\User;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;
    public $email, $password,$name;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email, $password,$name)
    {
        $this->email = $email;
        $this->password = $password;
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.users.passwordReset');
    }
}
