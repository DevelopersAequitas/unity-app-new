<?php

namespace App\Http\Resources;

use App\Models\CircleCategoryLevel4;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MutualConnectionResource extends JsonResource
{
    /**
     * Transform a mutual connection user into the API response shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = auth('sanctum')->user() ?: $request->user();

        $isFollowing = false;
        if ($this->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $this->getAttribute('is_following');
        } elseif ($authUser && Schema::hasTable('user_follows')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $this->id;
            if ($authUserId !== $targetId) {
                $isFollowing = UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $targetId)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            }
        }

        $isPro = false;
        $rawVerified = $this->is_verified ?? null;
        if ($this->getAttribute('is_pro') !== null) {
            $isPro = (bool) $this->getAttribute('is_pro');
        } elseif ($rawVerified !== null && (bool) $rawVerified) {
            $isPro = true;
        } elseif (method_exists($this->resource, 'isPaidMember')) {
            $isPro = (bool) $this->resource->isPaidMember();
        } else {
            $status = strtolower(trim((string) ($this->effective_membership_status ?? $this->membership_status ?? '')));
            $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
        }

        $isVerified = false;
        if ($rawVerified !== null && $rawVerified !== '' && (bool) $rawVerified) {
            $isVerified = true;
        } elseif (method_exists($this->resource, 'isPaidMember')) {
            $isVerified = (bool) $this->resource->isPaidMember();
        } else {
            $status = strtolower(trim((string) ($this->effective_membership_status ?? $this->membership_status ?? '')));
            $isVerified = in_array($status, ['premium', 'paid', 'active'], true);
        }

        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            if (is_array($bookmarks)) {
                $isBookmark = in_array((string) $this->id, $bookmarks, true);
            }
        }

        $photoUrl = $this->profilePhotoUrl();
        $categoryName = $this->resolveLevel4Category();
        $location = $this->locationName();

        return [
            'id' => (string) $this->id,
            'uuid' => (string) $this->id,
            'name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'username' => $this->username ?? '',
            'profile_photo' => $photoUrl,
            'profile_photo_url' => $photoUrl,
            'profile_photo_image' => $photoUrl,
            'designation' => $this->designation ?? null,
            'headline' => $this->designation ?? '',
            'company' => $this->company_name ?? '',
            'company_name' => $this->company_name ?? '',
            
            'level4_category' => $categoryName,
            'category' => $categoryName,
            'business_sub_category' => $this->business_sub_category ?? null,
            'business_type' => $this->business_type ?? $this->company_type ?? null,
            'city' => $location,
            'location' => $location,
            'mutual_count' => 0,
            'life_impacted_count' => (int) ($this->life_impacted_count ?? $this->coins_balance ?? 0),
            'is_following' => $isFollowing,
            'is_pro' => $isPro,
            'is_verified' => $isVerified,
            'is_connected' => true,
            'connection_status' => 'connected',
            'is_requested' => false,
            'can_send_connection_request' => false,
            'is_bookmark' => $isBookmark,
        ];
    }

    /**
     * Resolve Level 4 Category Name with multi-layer fallbacks.
     */
    private function resolveLevel4Category(): ?string
    {
        if ($this->relationLoaded('level4Category') && $this->level4Category) {
            return $this->level4Category->name ?? null;
        }

        if (filled($this->business_sub_category)) {
            return trim((string) $this->business_sub_category);
        }

        if (! empty($this->business_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            $cat = CircleCategoryLevel4::find($this->business_category_id);
            if ($cat && filled($cat->name)) {
                return trim((string) $cat->name);
            }
        }

        if ($this->relationLoaded('circleMembers')) {
            $membership = $this->circleMembers->first();
            if ($membership) {
                if ($membership->relationLoaded('level4Category') && $membership->level4Category) {
                    return $membership->level4Category->name ?? null;
                }
                if (! empty($membership->level_4_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
                    $cat = CircleCategoryLevel4::find($membership->level_4_category_id);
                    if ($cat && filled($cat->name)) {
                        return trim((string) $cat->name);
                    }
                }
            }
        }

        if (Schema::hasTable('circle_members') && Schema::hasColumn('circle_members', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('circle_members')
                    ->where('user_id', (string) $this->id)
                    ->whereNotNull('level_4_category_id')
                    ->where('level_4_category_id', '>', 0)
                    ->value('level_4_category_id');

                if ($level4Id) {
                    $name = DB::table('circle_category_level4')->where('id', $level4Id)->value('name');
                    if (filled($name)) {
                        return trim((string) $name);
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Resolve the display name used in mutual connection payloads.
     */
    private function displayName(): string
    {
        $name = trim((string) ($this->display_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($this->first_name ?? '').' '.(string) ($this->last_name ?? ''));
    }

    /**
     * Resolve the existing file-backed profile photo URL when available.
     */
    private function profilePhotoUrl(): string
    {
        if ($this->profile_photo_file_id) {
            return url('/api/v1/files/'.$this->profile_photo_file_id);
        }

        return (string) ($this->profile_photo_url ?? '');
    }

    /**
     * Resolve the best available location string.
     */
    private function locationName(): ?string
    {
        if ($this->relationLoaded('city')) {
            $cityRelation = $this->getRelation('city');

            if (is_object($cityRelation)) {
                return $cityRelation->name ?? null;
            }
        }

        $city = $this->getAttribute('city');

        if (is_object($city)) {
            return $city->name ?? null;
        }

        if (is_array($city)) {
            return $city['name'] ?? null;
        }

        if (is_string($city)) {
            return $city;
        }

        return null;
    }
}
