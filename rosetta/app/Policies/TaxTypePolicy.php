<?php

namespace Rosetta\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rosetta\Models\User;

class TaxTypePolicy
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
    public function lookup(User $user)
    {
        if($user->hasPermissionTo(['tax_type_lookup']))
            return true;
        return false;
    }
}
