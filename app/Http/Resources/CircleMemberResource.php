<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\City;
use App\Models\Connection;
use App\Models\JoinedCircleCategory;
use App\Models\UserFollow;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class CircleMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'circle_id' => $this->circle_id,
            'role' => $this->role,
            'status' => $this->status,
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'substitute_count' => $this->substitute_count,
            'role_id' => $this->role_id,

            'user' => $this->whenLoaded('user', function () use ($request) {
                $user = $this->user;
                $cityName = $this->resolveCityName($user);
                $categories = $this->resolveJoinedCircleCategories($user);
                $primaryCategory = $categories[0]['level1_category'] ?? null;
                $categoryId = $primaryCategory['id'] ?? $user?->business_category_id ?? $user?->main_business_category_id ?? null;
                $categoryName = $primaryCategory['name']
                    ?? $user?->businessCategory?->name
                    ?? $user?->mainBusinessCategory?->name
                    ?? null;
                $photoFileId = data_get($user, 'profile_photo_file_id')
                    ?: data_get($user, 'image_file_id')
                    ?: data_get($user, 'avatar_file_id')
                    ?: data_get($user, 'profile_image_file_id')
                    ?: data_get($user, 'photo_file_id')
                    ?: data_get($user, 'profile_file_id');

                $photoUrl = $photoFileId
                    ? url("/api/v1/files/{$photoFileId}")
                    : ($user?->profile_photo_url ?? null);

                $name = $user?->name
                    ?? $user?->display_name
                    ?? trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''))
                    ?: $user?->email;

                $authUser = auth('sanctum')->user() ?: ($request ? $request->user() : null);

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

                $isConnected = false;
                $connectionStatus = 'none';
                $isRequested = false;
                $canSendConnectionRequest = true;

                if ($user?->getAttribute('is_connected') !== null) {
                    $isConnected = (bool) $user->getAttribute('is_connected');
                    $connectionStatus = $user->getAttribute('connection_status') ?? ($isConnected ? 'connected' : 'none');
                    $isRequested = (bool) $user->getAttribute('is_requested');
                    $canSendConnectionRequest = (bool) ($user->getAttribute('can_send_connection_request') ?? (! $isConnected && ! $isRequested));
                } elseif ($authUser && $user && Schema::hasTable('connections')) {
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
                if ($authUser && $user) {
                    $bookmarks = $authUser->bookmarks ?? [];
                    if (is_array($bookmarks)) {
                        $isBookmark = in_array((string) $user->id, $bookmarks, true);
                    }
                }

                return [
                    'id' => $user?->id,
                    'name' => $name,
                    'email' => $user?->email,
                    'phone' => $user?->phone ?? null,
                    'country_code' => $user?->country_code ?? null,
                    'city_id' => $user?->city_id,
                    'city_name' => $cityName,
                    'city' => $cityName,
                    'business_category_id' => $categoryId,
                    'business_category_name' => $categoryName,
                    'business_category' => $categoryName,
                    'business_sub_category' => $user?->business_sub_category,
                    'level4_category' => $primaryCategory['name'] ?? $user?->level4Category?->name ?? $user?->business_sub_category ?? null,
                    'categories' => $categories,
                    'membership_status' => $user?->membership_status ?? null,
                    'life_impacted_count' => (int) ($user?->life_impacted_count ?? 0),
                    'is_active' => $user?->is_active ?? null,
                    'is_following' => $isFollowing,
                    'is_pro' => $isPro,
                    'is_connected' => $isConnected,
                    'is_bookmark' => $isBookmark,
                    'connection_status' => $connectionStatus,
                    'is_requested' => $isRequested,
                    'can_send_connection_request' => $canSendConnectionRequest,
                    'profile_photo_file_id' => $photoFileId,
                    'profile_photo_url' => $photoUrl,
                    'designation' => $user?->designation ?? null,
                    'company_name' => $user?->company_name ?? null,
                    'created_at' => optional($user?->created_at)->toISOString(),
                ];
            }),

            'role_details' => $this->whenLoaded('roleModel', function () {
                return [
                    'id' => $this->roleModel->id,
                    'name' => $this->roleModel->name ?? null,
                    'slug' => $this->roleModel->slug ?? null,
                ];
            }),
        ];
    }

    private function resolveCityName($user): ?string
    {
        if (! $user) {
            return null;
        }

        $cityRelation = $user->relationLoaded('city')
            ? $user->getRelationValue('city')
            : ($user->relationLoaded('cityRelation') ? $user->getRelationValue('cityRelation') : null);

        if ($cityRelation instanceof City || is_object($cityRelation)) {
            return $cityRelation->name ?? null;
        }

        $city = $user->getAttribute('city');

        if (is_object($city)) {
            return $city->name ?? null;
        }

        if (is_string($city) && $city !== '') {
            return $city;
        }

        $cityName = $user->getAttribute('city_name');

        if (is_string($cityName) && $cityName !== '') {
            return $cityName;
        }

        $cityOfResidence = $user->getAttribute('city_of_residence');

        return is_string($cityOfResidence) && $cityOfResidence !== '' ? $cityOfResidence : null;
    }

    private function resolveJoinedCircleCategories($user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->relationLoaded('joinedCircleCategories')) {
            $rows = $user->getRelationValue('joinedCircleCategories');
        } elseif (Schema::hasTable('joined_circle_categories')) {
            $rows = JoinedCircleCategory::query()
                ->where('user_id', $user->id)
                ->with([
                    'circle:id,name',
                    'level1Category:id,name',
                    'level2Category:id,name',
                    'level3Category:id,name',
                    'level4Category:id,name',
                ])
                ->orderByDesc('updated_at')
                ->get();
        } else {
            return [];
        }

        return $rows
            ->map(function (JoinedCircleCategory $row): array {
                return [
                    'circle_id' => $row->circle_id,
                    'circle_name' => $row->circle?->name,
                    'level1_category' => $row->level1Category
                        ? ['id' => $row->level1Category->id, 'name' => $row->level1Category->name]
                        : null,
                    'level2_category' => $row->level2Category
                        ? ['id' => $row->level2Category->id, 'name' => $row->level2Category->name]
                        : null,
                    'level3_category' => $row->level3Category
                        ? ['id' => $row->level3Category->id, 'name' => $row->level3Category->name]
                        : null,
                    'level4_category' => $row->level4Category
                        ? ['id' => $row->level4Category->id, 'name' => $row->level4Category->name]
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
