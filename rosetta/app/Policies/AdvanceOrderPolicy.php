<?php

namespace Rosetta\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rosetta\Models\User;

class AdvanceOrderPolicy
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
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createAdvanceOrders']))
            return true;
        return false;
    }
    public function update(User $user)
    {
        if($user->hasPermissionTo(['updateAdvanceOrders']))
            return true;
        return false;
    }
    public function view(User $user)
    {
        if($user->hasPermissionTo(['viewAdvanceOrders']))
            return true;
        return false;
    }
    public function deleteLine(User $user)
    {
        if($user->hasPermissionTo(['deleteAdvanceLines']))
            return true;
        return false;
    }
    public function deleteItem(User $user)
    {
        if($user->hasPermissionTo(['deleteAdvanceItems']))
            return true;
        return false;
    }
    public function updateLine(User $user)
    {
        if($user->hasPermissionTo(['updateAdvanceLines']))
            return true;
        return false;
    }
}
