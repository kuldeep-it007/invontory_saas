<?php

namespace Rosetta\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rosetta\Models\User;

class StockPointPolicy
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
    public function viewStockPoints(User $user)
    {
        if($user->hasPermissionTo(['viewStockPoints']))
            return true;
        return false;
    }
    public function viewStockPointCustomers(User $user)
    {
        if($user->hasPermissionTo(['viewStockPointCustomers']))
            return true;
        return false;
    }
    public function addStockPoint(User $user)
    {
        if($user->hasPermissionTo(['addStockPoint']))
            return true;
        return false;
    }
    public function deleteStockPointCustomers(User $user)
    {
        if($user->hasPermissionTo(['deleteStockPointCustomers']))
            return true;
        return false;
    }
    public function addStockPointCustomers(User $user)
    {
        if($user->hasPermissionTo(['addStockPointCustomers']))
            return true;
        return false;
    }
}
