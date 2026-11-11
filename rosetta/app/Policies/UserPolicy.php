<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
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
    public function update(User $user)
    {
        if($user->hasPermissionTo(['updateUser']))
            return true;
        return false;
    }
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createUser']))
            return true;
        return false;
    }
    public function view(User $user)
    {
        if($user->hasPermissionTo(['viewUser']))
            return true;
        return false;
    }
    public function delete(User $user)
    {
        if($user->hasPermissionTo(['deleteUser']))
            return true;
        return false;
    }
    public function impersonate(User $user)
    {
        if($user->hasPermissionTo(['impersonate']))
            return true;
        return false;
    }
}
