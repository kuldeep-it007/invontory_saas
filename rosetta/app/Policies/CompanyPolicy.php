<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
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
    public function companies(User $user)
    {
        if($user->hasPermissionTo(['companies']))
            return true;
        return false;
    }
}
