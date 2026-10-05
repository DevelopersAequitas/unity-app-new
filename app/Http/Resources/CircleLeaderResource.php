<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\City;
use App\Models\Connection;
use App\Models\JoinedCircleCategory;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class CircleLeaderResource extends JsonResource
{
    /**
     * Transform the resource into an array adhering to canonical Peer contract (Developer Rules Section 9).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : (is_object($this->resource) ? (array) $this->resource : []);

        $user = data_get($this->resource, 'user');
        $rawUser = $user instanceof User ? $user : null;
        $userId = $rawUser ? (string) $rawUser->id : (string) (data_get($data, 'user_id') ?: data_get($data, 'id'));

        if (! $rawUser && $userId !== '') {
            $rawUser = User::with(['cityRelation', 'businessCategory', 'mainBusinessCategory'])->find($userId);
        }

        $peerData = $this->resolvePeerData($rawUser, $request, $data);

        $roleSlug = (string) (data_get($data, 'role') ?: data_get($data, 'role_slug') ?: 'leader');
        $roleName = (string) (data_get($data, 'role_name') ?: data_get($data, 'designation') ?: data_get($data, 'role') ?: 'Leader');
        $designation = (string) (data_get($data, 'designation') ?: $roleName);

        $name = $peerData['name'] ?? data_get($data, 'name');
        $displayName = $peerData['display_name'] ?? data_get($data, 'display_name') ?? $name;
        $firstName = $peerData['first_name'] ?? data_get($data, 'first_name');
        $lastName = $peerData['last_name'] ?? data_get($data, 'last_name');
        $email = $rawUser?->email ?? data_get($data, 'email');
        $phone = $rawUser?->phone ?? data_get($data, 'phone');
        $photoUrl = $peerData['profile_photo_url'] ?? data_get($data, 'profile_photo_url');
        $companyName = $peerData['company_name'] ?? data_get($data, 'company_name');
        $isOnline = (bool) ($peerData['is_online'] ?? data_get($data, 'is_online', false));
        $region = data_get($data, 'region') ?? $peerData['city'] ?? null;

        return [
            'id' => data_get($data, 'id') ?: ($rawUser ? (string) $rawUser->id : null),
            'circle_id' => data_get($data, 'circle_id'),
            'user_id' => $rawUser ? (string) $rawUser->id : (data_get($data, 'user_id') ? (string) data_get($data, 'user_id') : null),
            'role' => $roleSlug,
            'role_name' => $roleName,
            'designation' => $designation,
            'is_assigned' => $rawUser !== null || ! empty(data_get($data, 'name')),
            'region' => $region,
            'chapter' => data_get($data, 'chapter'),
            'training_info' => data_get($data, 'training_info'),

            // Canonical Peer object (Section 9 DEVELOPER_RULES.md)
            'user' => $peerData,
            'peer' => $peerData,

            // Top-level properties
            'name' => $name,
            'display_name' => $displayName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'profile_photo_url' => $photoUrl,
            'profile_photo_image' => $photoUrl,
            'company_name' => $companyName,
            'is_online' => $isOnline,
        ];
    }

    /**
     * Build canonical peer data array.
     */
    private function resolvePeerData(?User $user, Request $request, array $fallbackData): ?array
    {
        if (! $user && empty(data_get($fallbackData, 'name'))) {
            return null;
        }

        $cityName = null;
        $countryCode = 'IN';

        if ($user) {
            $cityRelation = $user->relationLoaded('city')
                ? $user->getRelation('city')
                : ($user->relationLoaded('cityRelation') ? $user->getRelation('cityRelation') : null);

            if ($cityRelation instanceof City) {
                $cityName = $cityRelation->name;
                $countryCode = $cityRelation->country_code ?: ($cityRelation->country ? ($cityRelation->country === 'India' ? 'IN' : strtoupper(substr((string) $cityRelation->country, 0, 2))) : 'IN');
            } else {
                $cityName = is_string($user->city) ? $user->city : ($user->city_of_residence ?? null);
                if (! empty($user->country)) {
                    $countryCode = $user->country === 'India' ? 'IN' : strtoupper(substr((string) $user->country, 0, 2));
                }
            }
        } else {
            $cityName = data_get($fallbackData, 'city') ?: data_get($fallbackData, 'region');
        }

        $formattedCity = null;
        if (filled($cityName)) {
            $cityName = trim((string) $cityName);
            $formattedCity = str_contains($cityName, ',') ? $cityName : "{$cityName}, {$countryCode}";
        }

        $photoFileId = $user ? (
            data_get($user, 'profile_photo_file_id')
            ?: data_get($user, 'image_file_id')
            ?: data_get($user, 'avatar_file_id')
            ?: data_get($user, 'profile_image_file_id')
            ?: data_get($user, 'photo_file_id')
            ?: data_get($user, 'profile_file_id')
        ) : data_get($fallbackData, 'profile_photo_file_id');

        $photoUrl = $photoFileId
            ? url("/api/v1/files/{$photoFileId}")
            : ($user?->profile_photo_url ?? data_get($fallbackData, 'profile_photo_url') ?? data_get($fallbackData, 'profile_photo_image'));

        $name = $user?->name
            ?? $user?->display_name
            ?? trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''))
            ?: (data_get($fallbackData, 'name') ?: data_get($fallbackData, 'display_name') ?: $user?->email);

        $authUser = auth('sanctum')->user() ?: $request->user();

        $isBookmark = false;
        if ($authUser && $user) {
            $bookmarks = $authUser->bookmarks ?? [];
            $isBookmark = in_array((string) $user->id, (array) $bookmarks, true);
        }

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

        $rawVerified = $user?->is_verified ?? null;
        if ($rawVerified !== null) {
            $isVerified = (bool) $rawVerified;
        } elseif ($user && method_exists($user, 'isPaidMember')) {
            $isVerified = (bool) $user->isPaidMember();
        } else {
            $isVerified = false;
        }

        $isPro = false;
        if ($user?->getAttribute('is_pro') !== null) {
            $isPro = (bool) $user->getAttribute('is_pro');
        } elseif ($rawVerified !== null && (bool) $rawVerified) {
            $isPro = true;
        } elseif ($user && method_exists($user, 'isPaidMember')) {
            $isPro = (bool) $user->isPaidMember();
        } elseif ($user) {
            $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
            $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
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

        $categories = $this->resolveJoinedCircleCategories($user);
        $primaryCategory = $categories[0]['level1_category'] ?? null;
        $categoryId = $primaryCategory['id'] ?? $user?->business_category_id ?? $user?->main_business_category_id ?? null;
        $categoryName = $primaryCategory['name']
            ?? $user?->businessCategory?->name
            ?? $user?->mainBusinessCategory?->name
            ?? null;

        $level4Category = $user?->relationLoaded('level4Category') && $user?->level4Category
            ? $user->level4Category->name
            : ($primaryCategory['name'] ?? $user?->level4Category?->name ?? $user?->business_sub_category ?? $user?->getAttribute('level4_category') ?? null);

        return [
            'id' => $user ? (string) $user->id : data_get($fallbackData, 'id'),
            'name' => $name !== '' ? trim((string) $name) : null,
            'display_name' => $user?->display_name ?: ($name !== '' ? trim((string) $name) : null),
            'first_name' => $user?->first_name ?? data_get($fallbackData, 'first_name'),
            'last_name' => $user?->last_name ?? data_get($fallbackData, 'last_name'),
            'city' => $formattedCity ?: $cityName,
            'city_id' => $user?->city_id,
            'city_name' => $cityName,
            'company_name' => $user?->company_name ?? data_get($fallbackData, 'company_name'),
            'life_impacted_count' => (int) ($user?->life_impacted_count ?? 0),
            'introduced_count' => (int) ($user?->introduced_count ?? ($user?->relationLoaded('introducedPeers') ? $user->introducedPeers->count() : ($user?->members_introduced_count ?? 0))),
            'profile_photo_image' => $photoUrl,
            'profile_photo_url' => $photoUrl,
            'profile_photo_file_id' => $photoFileId,
            'membership_status' => $user?->effective_membership_status ?? $user?->membership_status,
            'designation' => $user?->designation ?? data_get($fallbackData, 'designation'),
            'level4_category' => $level4Category,
            'business_category_id' => $categoryId,
            'business_category_name' => $categoryName,
            'business_category' => $categoryName,
            'business_sub_category' => $user?->business_sub_category,
            'categories' => $categories,
            'is_bookmark' => $isBookmark,
            'is_following' => $isFollowing,
            'is_verified' => $isVerified,
            'is_pro' => $isPro,
            'is_online' => (bool) ($user?->is_online ?? data_get($fallbackData, 'is_online', false)),
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
            'match_percentage' => (int) ($user?->match_percentage ?? 0),
            'email' => $user?->email ?? data_get($fallbackData, 'email'),
            'phone' => $user?->phone ?? data_get($fallbackData, 'phone'),
            'country_code' => $user?->country_code ?? null,
            'is_active' => $user?->is_active ?? null,
            'created_at' => optional($user?->created_at)->toISOString(),
        ];
    }

    private function resolveJoinedCircleCategories(?User $user): array
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
