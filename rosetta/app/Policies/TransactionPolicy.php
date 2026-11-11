<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
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


    public function manage(User $user)
    {
        if($user->hasPermissionTo(['manageTransactions']))
            return true;
        return false;
    }
}
