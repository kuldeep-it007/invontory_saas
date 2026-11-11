<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DistributorPolicy
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
        if($user->hasPermissionTo(['viewDistributors']))
            return true;
        return false;
    }
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createDistributors']))
            return true;
        return false;
    }
    public function update(User $user)
    {
        if($user->hasPermissionTo(['updateDistributors']))
            return true;
        return false;
    }
    public function profile(User $user)
    {
        if($user->hasPermissionTo(['manageProfile']))
            return true;
        return false;
    }
    public function delete(User $user)
    {
        if($user->hasPermissionTo(['deleteDistributors']))
            return true;
        return false;
    }
}
