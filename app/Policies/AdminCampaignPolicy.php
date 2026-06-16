<?php

namespace App\Policies;

use App\Models\AdminCampaign;
use App\Support\AdminAccess;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminCampaignPolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user has correct admin access roles.
     */
    private function hasAdminAccess($user): bool
    {
        if (!$user) {
            return false;
        }

        $roleKeys = AdminAccess::adminRoleKeys($user);
        $allowedRoles = ['global_admin', 'industry_director', 'ded', 'circle_leader'];

        return !empty(array_intersect($allowedRoles, $roleKeys));
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        return $this->hasAdminAccess($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        return $this->hasAdminAccess($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user);
    }

    /**
     * Determine whether the user can pause the model.
     */
    public function pause($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user) && in_array($campaign->status, [
            AdminCampaign::STATUS_SCHEDULED,
            AdminCampaign::STATUS_ACTIVE
        ], true);
    }

    /**
     * Determine whether the user can resume the model.
     */
    public function resume($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user) && $campaign->status === AdminCampaign::STATUS_PAUSED;
    }

    /**
     * Determine whether the user can stop the model.
     */
    public function stop($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user) && in_array($campaign->status, [
            AdminCampaign::STATUS_ACTIVE,
            AdminCampaign::STATUS_SCHEDULED,
            AdminCampaign::STATUS_PAUSED
        ], true);
    }

    /**
     * Determine whether the user can duplicate the model.
     */
    public function duplicate($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user) && in_array($campaign->status, [
            AdminCampaign::STATUS_SENT,
            AdminCampaign::STATUS_COMPLETED
        ], true);
    }

    /**
     * Determine whether the user can retry the model.
     */
    public function retry($user, AdminCampaign $campaign): bool
    {
        return $this->hasAdminAccess($user) && in_array($campaign->status, [
            AdminCampaign::STATUS_FAILED,
            AdminCampaign::STATUS_STOPPED
        ], true);
    }
}
