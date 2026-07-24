<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Auth\Access\HandlesAuthorization;

class SponsorshipMilestonePolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user has correct admin access roles.
     */
    private function hasAdminAccess(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        $admin = null;
        if ($user instanceof AdminUser) {
            $admin = $user;
        } elseif ($user instanceof User) {
            $admin = new AdminUser;
            $admin->id = $user->id;
            $admin->email = $user->email;
        } else {
            return false;
        }

        $roleKeys = AdminAccess::adminRoleKeys($admin);
        $allowedRoles = ['global_admin', 'industry_director', 'ded', 'circle_leader'];

        return ! empty(array_intersect($allowedRoles, $roleKeys));
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(mixed $user): bool
    {
        return $this->hasAdminAccess($user);
    }

    /**
     * Determine whether the user can view the milestones of a specific member.
     */
    public function view(mixed $user): bool
    {
        return $this->hasAdminAccess($user);
    }
}
