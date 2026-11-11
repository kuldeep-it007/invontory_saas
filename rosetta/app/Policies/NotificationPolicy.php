<?php

namespace Rosetta\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rosetta\Models\User;

class NotificationPolicy
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
    public function send(User $user)
    {
        if($user->hasPermissionTo(['sendNotification']))
            return true;
        return false;
    }
}
