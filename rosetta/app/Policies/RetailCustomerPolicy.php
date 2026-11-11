<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RetailCustomerPolicy
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
        if($user->hasPermissionTo(['viewRetailCustomers']))
            return true;
        return false;
    }
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createRetailCustomers']))
            return true;
        return false;
    }
    public function update(User $user)
    {
        if($user->hasPermissionTo(['updateRetailCustomers']))
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
        if($user->hasPermissionTo(['deleteRetailCustomers']))
            return true;
        return false;
    }
}
