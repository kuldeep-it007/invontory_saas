<?php

namespace Rosetta\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\Company;
use Rosetta\Models\User;

class CompanyDealersCreated extends Mailable
{
    use Queueable, SerializesModels;
    public $company, $UserList;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company, $UserList)
    {
        $this->company = $company;
        $this->UserList = $UserList;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.dealers.companyDealersCreated');
    }
}
