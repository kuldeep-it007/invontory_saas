<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollectionPolicy
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
        if($user->hasPermissionTo(['viewCollections']))
            return true;
        return false;
    }
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createCollections']))
            return true;
        return false;
    }
}
