<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CircleCategoryLevel4;
use App\Models\City;
use App\Models\Connection;
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
        if (! $user) {
            return [];
        }

        $authUser = auth('sanctum')->user() ?: ($request ? $request->user() : null);

        $name = $user->name
            ?? $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        if (empty($name) && ! empty($user->email)) {
            $name = Str::before($user->email, '@');
        }

        $cityName = $this->resolveCity($user);
        $businessName = $user->company_name ?: ($user->business_name ?? null);
        $subCategory = $this->resolveSubCategory($user);

        $isFollowing = false;
        if ($user->getAttribute('is_following') !== null) {
            $isFollowing = (bool) $user->getAttribute('is_following');
        } elseif ($authUser && Schema::hasTable('user_follows')) {
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

        $isConnected = false;
        $connectionStatus = 'none';
        $isRequested = false;
        $canSendConnectionRequest = true;

        if ($user->getAttribute('is_connected') !== null) {
            $isConnected = (bool) $user->getAttribute('is_connected');
            $connectionStatus = (string) ($user->getAttribute('connection_status') ?? ($isConnected ? 'connected' : 'none'));
            $isRequested = (bool) $user->getAttribute('is_requested');
            $canSendConnectionRequest = (bool) ($user->getAttribute('can_send_connection_request') ?? (! $isConnected && ! $isRequested));
        } elseif ($authUser && Schema::hasTable('connections')) {
            $authUserId = (string) $authUser->id;
            $targetId = (string) $user->id;
            if ($authUserId === $targetId) {
                $connectionStatus = 'self';
                $canSendConnectionRequest = false;
            } else {
                $connection = Connection::query()
                    ->where(function ($q) use ($authUserId, $targetId) {
                        $q->where('requester_id', $authUserId)->where('addressee_id', $targetId);
                    })
                    ->orWhere(function ($q) use ($authUserId, $targetId) {
                        $q->where('addressee_id', $authUserId)->where('requester_id', $targetId);
                    })
                    ->first();

                if ($connection) {
                    $isConnected = (bool) $connection->is_approved;
                    $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
                    $connectionStatus = $isConnected
                        ? 'connected'
                        : ($isRequested ? 'pending_sent' : 'pending_received');
                    $canSendConnectionRequest = false;
                }
            }
        }

        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            if (is_array($bookmarks)) {
                $isBookmark = in_array((string) $user->id, $bookmarks, true);
            }
        }

        $isPro = false;
        $rawVerified = $user->is_verified ?? null;
        if ($rawVerified !== null && (bool) $rawVerified) {
            $isPro = true;
        } elseif (method_exists($user, 'isPaidMember')) {
            $isPro = (bool) $user->isPaidMember();
        } else {
            $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
            $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
        }

        $photoUrl = $this->buildProfileImageUrl($user);
        $membershipStatus = $user->effective_membership_status ?? $user->membership_status ?? null;

        return [
            'id' => (string) $user->id,
            'peer_id' => (string) ($user->peer_id ?? $user->id),
            'name' => $name !== '' ? trim((string) $name) : null,
            'display_name' => $user->display_name ?: ($name !== '' ? trim((string) $name) : null),
            'first_name' => $user->first_name ?? null,
            'last_name' => $user->last_name ?? null,
            'profile_photo_url' => $photoUrl,
            'profile_image_url' => $photoUrl,
            'profile_photo_image' => $photoUrl,
            'avatar_url' => $photoUrl,
            'company_name' => $businessName,
            'business_name' => $businessName,
            'city' => $cityName,
            'city_name' => $cityName,
            'membership_status' => $membershipStatus,
            'designation' => $user->designation ?? $user->job_title ?? null,
            'business_category' => $subCategory,
            'business_category_name' => $subCategory,
            'level4_category' => $subCategory,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'introduced_count' => (int) ($user->introduced_count ?? ($user->relationLoaded('introducedPeers') ? $user->introducedPeers->count() : ($user->members_introduced_count ?? 0))),
            'is_following' => $isFollowing,
            'is_bookmark' => $isBookmark,
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
            'is_pro' => $isPro,
            'is_verified' => (bool) ($rawVerified ?? false),
            'is_online' => (bool) ($user->is_online ?? false),
        ];
    }

    private function resolveCity($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('city')) {
            $cityRelation = $user->getRelation('city');
            if ($cityRelation instanceof City) {
                return $cityRelation->name ?? null;
            }
        }

        if ($user->relationLoaded('cityRelation')) {
            $cityRelation = $user->getRelation('cityRelation');
            if ($cityRelation instanceof City) {
                return $cityRelation->name ?? null;
            }
        }

        $city = $user->getAttribute('city');
        if (is_array($city)) {
            return $city['name'] ?? null;
        }
        if (is_object($city)) {
            return $city->name ?? null;
        }
        if (is_string($city) && trim($city) !== '') {
            $trimmedCity = trim($city);
            if (str_starts_with($trimmedCity, '{')) {
                $decoded = json_decode($trimmedCity, true);
                if (is_array($decoded) && ! empty($decoded['name'])) {
                    return trim((string) $decoded['name']);
                }
            }

            return $trimmedCity;
        }

        return null;
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

    private function buildProfileImageUrl($user): ?string
    {
        $fileId = $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? $user->profile_image_id
            ?? null;

        if ($fileId) {
            return url('/api/v1/files/'.$fileId);
        }

        return $user->profile_photo_url ?? null;
    }
}

