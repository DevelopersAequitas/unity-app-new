<?php

namespace App\Http\Resources;

use App\Models\CircleCategoryLevel4;
use App\Models\UserFollow;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserMiniResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $this->resource;
        $authUser = auth('sanctum')->user() ?: ($request ? $request->user() : null);

        $name = $user->name
            ?? $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        if (empty($name) && ! empty($user->email)) {
            $name = Str::before($user->email, '@');
        }

        $subCategory = $this->resolveSubCategory($user);

        $isFollowing = false;
        if ($user?->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $user->getAttribute('is_following');
        } elseif ($authUser && $user && Schema::hasTable('user_follows')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $user->id;
            if ($authUserId !== $targetId) {
                $isFollowing = UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $targetId)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            }
        }

        $isPro = false;
        if ($user) {
            $rawVerified = $user->is_verified ?? null;
            if ($rawVerified !== null && (bool) $rawVerified) {
                $isPro = true;
            } elseif (method_exists($user, 'isPaidMember')) {
                $isPro = (bool) $user->isPaidMember();
            } else {
                $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
                $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
            }
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? trim((string) $name) : null,
            'profile_image_url' => $this->buildProfileImageUrl(),
            'profile_photo_url' => $this->buildProfileImageUrl(),
            'company_name' => $user->company_name,
            'city' => $user->city,
            'designation' => $user->designation ?? $user->job_title ?? null,
            'level4_category' => $subCategory,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'is_following' => $isFollowing,
            'is_pro' => $isPro,
        ];
    }

    private function resolveSubCategory($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('level4Category') && $user->level4Category) {
            return $user->level4Category->name ?? null;
        }

        if (filled($user->business_sub_category)) {
            return trim((string) $user->business_sub_category);
        }

        if (! empty($user->business_category_id) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            $cat = CircleCategoryLevel4::find($user->business_category_id);
            if ($cat && filled($cat->name)) {
                return trim((string) $cat->name);
            }
        }

        if ($user->relationLoaded('circleMembers')) {
            $membership = $user->circleMembers->first();
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
                    ->where('user_id', (string) $user->id)
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

    private function buildProfileImageUrl(): ?string
    {
        $user = $this->resource;

        $fileId = $user->profile_image_id
            ?? $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? null;

        if (! $fileId) {
            return null;
        }

        return url('/api/v1/files/'.$fileId);
    }
}
