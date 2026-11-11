<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DealerPolicy
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
        if($user->hasPermissionTo('viewDealers'))
            return true;
        return false;
    }
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createDealers']))
            return true;
        return false;
    }
    public function update(User $user)
    {
        if($user->hasPermissionTo(['updateDealers']))
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
        if($user->hasPermissionTo(['deleteDealers']))
            return true;
        return false;
    }
    public function approve(User $user)
    {
        if($user->hasPermissionTo(['approveDealers']))
            return true;
        return false;
    }
    public function reject(User $user)
    {
        if($user->hasPermissionTo(['rejectDealers']))
            return true;
        return false;
    }
}
