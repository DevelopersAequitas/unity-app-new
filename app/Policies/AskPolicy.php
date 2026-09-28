<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ask\Ask;
use App\Models\User;

class AskPolicy
{
    /**
     * Determine whether the user can view the Ask.
     */
    public function view(User $user, Ask $ask): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the Ask.
     */
    public function update(User $user, Ask $ask): bool
    {
        return (string) $ask->user_id === (string) $user->id;
    }

    /**
     * Determine whether the user can publish the Ask.
     */
    public function publish(User $user, Ask $ask): bool
    {
        return (string) $ask->user_id === (string) $user->id;
    }

    /**
     * Determine whether the user can update status / fulfill the Ask.
     */
    public function updateStatus(User $user, Ask $ask): bool
    {
        return (string) $ask->user_id === (string) $user->id;
    }

    /**
     * Determine whether the user can delete the Ask.
     */
    public function delete(User $user, Ask $ask): bool
    {
        return (string) $ask->user_id === (string) $user->id;
    }
}
