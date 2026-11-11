<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Rosetta\Models\Company;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnlineDiscountsPolicy
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
    public function listDiscounts(User $user, Company $company)
    {
        if(!$user->hasPermissionTo('viewOnlineDiscounts'))
        {
            return false;
        }
        
        if($user->company)
        {
            if($user->company->id == $company->id)
            {
                return true;
            }
            return false;
        }
        return true;
    }
    public function delete(User $user, Company $company)
    {
        if(!$user->hasPermissionTo('deleteOnlineDiscount'))
        {
            return false;
        }
        
        if($user->company)
        {
            if($user->company->id == $company->id)
            {
                return true;
            }
            return false;
        }
        return true;
    }

    public function create(User $user, Company $company)
    {
        if(!$user->hasPermissionTo('addOnlineDiscount'))
        {
            return false;
        }
        
        if($user->company)
        {
            if($user->company->id == $company->id)
            {
                return true;
            }
            return false;
        }
        return true;
    }
}
