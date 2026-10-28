<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpeedNetworkingEventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any events.
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view the event.
     */
    public function view(?User $user, SpeedNetworkingEvent $event): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(?User $user, SpeedNetworkingEvent $event): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(?User $user, SpeedNetworkingEvent $event): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can manage live sessions (start, pause, resume, next-round, end, rematch).
     */
    public function manage(?User $user, SpeedNetworkingEvent $event): bool
    {
        return $user !== null;
    }
}

