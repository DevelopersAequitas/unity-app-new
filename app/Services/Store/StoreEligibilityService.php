<?php

namespace App\Services\Store;

use App\Models\Store\Product;
use App\Models\User;

class StoreEligibilityService
{
    public function isEligible(Product $product, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $eligibility = $product->eligibility;
        if (empty($eligibility) || ! is_array($eligibility)) {
            return true;
        }

        // 1. Membership status check
        if (! empty($eligibility['allowed_membership_statuses']) && is_array($eligibility['allowed_membership_statuses'])) {
            if (! in_array($user->membership_status, $eligibility['allowed_membership_statuses'], true)) {
                return false;
            }
        }

        // 2. Allowed peer roles / statuses
        if (! empty($eligibility['allowed_peer_types']) && is_array($eligibility['allowed_peer_types'])) {
            if (! in_array($user->status, $eligibility['allowed_peer_types'], true)) {
                return false;
            }
        }

        // 3. Min membership days or active check
        if (! empty($eligibility['requires_active_membership']) && $eligibility['requires_active_membership'] === true) {
            if ($user->membership_status !== 'active' && $user->membership_status !== 'ACTIVE') {
                return false;
            }
        }

        return true;
    }
}
