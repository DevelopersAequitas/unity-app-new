<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserProfileResource extends MemberDetailResource
{
    /**
     * Transform the resource into an array with duplicate fields removed.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        // Ensure introducer ID is preserved in introduced_by_user before unsetting introduced_by
        if (empty($data['introduced_by_user']) && ! empty($data['introduced_by'])) {
            $data['introduced_by_user'] = [
                'id' => (string) $data['introduced_by'],
                'name' => null,
                'profile_photo_url' => null,
            ];
        }

        // Ensure business_website has fallback before unsetting website & other_website
        if (empty($data['business_website'])) {
            $data['business_website'] = $this->business_website ?? $data['website'] ?? $this->other_website ?? null;
        }

        // Remove all duplicate and redundant fields
        unset(
            // Count aliases (duplicates of badges_count, p2p_meetings_count, business_deals_count)
            $data['my_badges_count'],
            $data['p2p_count'],
            $data['deals_count'],

            // Profile video duplicate (profile_video_id & profile_video_url are canonical)
            $data['profile_video'],

            // Introducer UUID (already present inside introduced_by_user.id)
            $data['introduced_by'],

            // Active circle flat fields (already present in active_circle and circle_memberships)
            $data['active_circle_id'],
            $data['active_circle_addon_code'],
            $data['active_circle_addon_name'],
            $data['circle_joined_at'],
            $data['circle_expires_at'],
            $data['active_circle_subscription_id'],

            // Category duplicates (already present in business_category & main_business_category)
            $data['business_sub_category'],
            $data['main_business_category_id'],
            $data['business_category_id'],

            // Website duplicates (already present in business_website)
            $data['website'],
            $data['other_website'],

            // Social links object (duplicates flat fields linkedin_profile, instagram_handle, etc.)
            $data['social_links'],

            // Categories field (category path is now properly inside circle_memberships)
            $data['categories']
        );

        return $data;
    }
}
