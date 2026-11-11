<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }


    public function view(User $user)
    {
        if($user->hasPermissionTo(['viewInvoices']))
            return true;
        return false;
    }
    public function print(User $user)
    {
        if($user->hasPermissionTo(['printInvoices']))
            return true;
        return false;
    }
}
