<?php

namespace Rosetta\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rosetta\Models\User;

class PurchaseOrderPolicy
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
        if($user->hasPermissionTo(['viewPurchaseOrders']))
            return true;
        return false;
    }
    public function updateLine(User $user)
    {
        if($user->hasPermissionTo(['updatePurchaseLines']))
            return true;
        return false;
    }
    public function deleteLine(User $user)
    {
        if($user->hasPermissionTo(['deletePurchaseLines']))
            return true;
        return false;
    }
}
