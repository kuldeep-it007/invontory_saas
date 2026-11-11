<?php

namespace Rosetta\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rosetta\Models\User;

class DepotOrderPolicy
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
        if($user->hasPermissionTo(['viewDepotOrders']))
            return true;
        return false;
    }
}
