<?php

namespace Rosetta\Policies;

use Rosetta\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ColourPolicy
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
        if($user->hasPermissionTo(['viewColors']))
            return true;
        return false;
    }
    public function create(User $user)
    {
        if($user->hasPermissionTo(['createColors']))
            return true;
        return false;
    }
}
