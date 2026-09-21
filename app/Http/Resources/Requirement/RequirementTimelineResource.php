<?php

declare(strict_types=1);

namespace App\Http\Resources\Requirement;

use App\Models\CircleCategoryLevel4;
use App\Models\Connection;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequirementTimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $creator = $this->whenLoaded('user');
        $authUser = auth('sanctum')->user() ?: ($request instanceof Request ? $request->user() : null);

        $userName = data_get($creator, 'name')
            ?: data_get($creator, 'full_name')
            ?: data_get($creator, 'display_name')
            ?: trim((string) data_get($creator, 'first_name', '').' '.(string) data_get($creator, 'last_name', ''));

        $company = data_get($creator, 'company') ?: data_get($creator, 'company_name', '');
        $city = is_string(data_get($creator, 'city'))
            ? data_get($creator, 'city')
            : (data_get($creator, 'city.name') ?: (string) data_get($creator, 'city_of_residence', ''));
        $profilePhotoUrl = $this->resolveProfilePhotoUrl($creator);

        $level4Category = $this->resolveLevel4Category($creator);
        $isVerified = $this->resolveIsVerified($creator);
        $isPro = $this->resolveIsPro($creator, $isVerified);
        $isBookmark = $this->resolveIsBookmark($creator, $authUser);
        $isFollowing = $this->resolveIsFollowing($creator, $authUser);

        $connectionInfo = $this->resolveConnectionInfo($creator, $authUser);
        $isConnected = $connectionInfo['is_connected'];
        $connectionStatus = $connectionInfo['connection_status'];
        $isRequested = $connectionInfo['is_requested'];
        $canSendConnectionRequest = $connectionInfo['can_send_connection_request'];

        $userData = [
            'id' => $this->user_id ?? data_get($creator, 'id'),
            'name' => $userName,
            'user_name' => $userName,
            'company' => $company,
            'company_name' => $company,
            'city' => $city,
            'profile_photo_url' => $profilePhotoUrl,
            'level4_category' => $level4Category,
            'is_bookmark' => $isBookmark,
            'is_following' => $isFollowing,
            'is_verified' => $isVerified,
            'is_pro' => $isPro,
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
        ];

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'user_id' => $this->user_id ?? data_get($creator, 'id'),
            'user_name' => $userName,
            'company' => $company,
            'city' => $city,
            'profile_photo_url' => $profilePhotoUrl,
            'level4_category' => $level4Category,
            'is_bookmark' => $isBookmark,
            'is_following' => $isFollowing,
            'is_verified' => $isVerified,
            'is_pro' => $isPro,
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
            'user' => $userData,
            'subject' => $this->subject,
            'description' => $this->description,
            'media' => collect($this->media ?? [])->map(function ($item) {
                if (is_string($item)) {
                    return ['type' => 'unknown', 'file_id' => null, 'url' => $item];
                }

                $fileId = data_get($item, 'file_id') ?: data_get($item, 'id');

                return [
                    'type' => data_get($item, 'type', 'image'),
                    'file_id' => $fileId,
                    'url' => data_get($item, 'url') ?: ($fileId ? url('/api/v1/files/'.$fileId) : null),
                ];
            })->values()->all(),
            'region_filter' => $this->region_filter ?? [],
            'category_filter' => $this->category_filter ?? [],
            'status' => $this->status,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }

    private function resolveProfilePhotoUrl(mixed $creator): ?string
    {
        if (! $creator) {
            return null;
        }

        $profilePhotoId = data_get($creator, 'profile_photo_id') ?: data_get($creator, 'profile_photo_file_id');

        if ($profilePhotoId) {
            return url('/api/v1/files/'.$profilePhotoId);
        }

        return data_get($creator, 'profile_photo_url');
    }

    private function resolveLevel4Category(mixed $creator): ?string
    {
        if (! $creator) {
            return null;
        }

        if ($creator instanceof User && $creator->getAttribute('level4_category') !== null) {
            return $creator->getAttribute('level4_category');
        }

        if (method_exists($creator, 'relationLoaded') && $creator->relationLoaded('level4Category') && $creator->level4Category) {
            return $creator->level4Category->name ?? null;
        }

        if (filled(data_get($creator, 'business_sub_category'))) {
            return trim((string) data_get($creator, 'business_sub_category'));
        }

        $categoryId = data_get($creator, 'business_category_id');
        if (! empty($categoryId) && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            $cat = CircleCategoryLevel4::find($categoryId);
            if ($cat && filled($cat->name)) {
                return trim((string) $cat->name);
            }
        }

        if (method_exists($creator, 'relationLoaded') && $creator->relationLoaded('circleMembers')) {
            $membership = $creator->circleMembers->first();
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

        $creatorId = data_get($creator, 'id');
        if ($creatorId && Schema::hasTable('circle_members') && Schema::hasColumn('circle_members', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('circle_members')
                    ->where('user_id', (string) $creatorId)
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

        if ($creatorId && Schema::hasTable('joined_circle_categories') && Schema::hasColumn('joined_circle_categories', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('joined_circle_categories')
                    ->where('user_id', (string) $creatorId)
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

        if (method_exists($creator, 'relationLoaded') && $creator->relationLoaded('businessCategory') && $creator->businessCategory) {
            return $creator->businessCategory->name ?? null;
        }

        return null;
    }

    private function resolveIsVerified(mixed $creator): bool
    {
        if (! $creator) {
            return false;
        }

        if ($creator instanceof User && $creator->getAttribute('is_verified') !== null) {
            return (bool) $creator->getAttribute('is_verified');
        }

        $raw = data_get($creator, 'is_verified');
        if ($raw !== null) {
            return (bool) $raw;
        }

        if (is_object($creator) && method_exists($creator, 'isPaidMember')) {
            return (bool) $creator->isPaidMember();
        }

        return false;
    }

    private function resolveIsPro(mixed $creator, bool $isVerified): bool
    {
        if (! $creator) {
            return false;
        }

        if ($creator instanceof User && $creator->getAttribute('is_pro') !== null) {
            return (bool) $creator->getAttribute('is_pro');
        }

        if ($isVerified) {
            return true;
        }

        if (is_object($creator) && method_exists($creator, 'isPaidMember')) {
            return (bool) $creator->isPaidMember();
        }

        $status = strtolower(trim((string) (data_get($creator, 'effective_membership_status') ?: data_get($creator, 'membership_status', ''))));

        return $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
    }

    private function resolveIsBookmark(mixed $creator, ?User $authUser): bool
    {
        if (! $creator) {
            return false;
        }

        if ($creator instanceof User && $creator->getAttribute('is_bookmark') !== null) {
            return (bool) $creator->getAttribute('is_bookmark');
        }

        if ($authUser && is_array($authUser->bookmarks)) {
            $creatorId = (string) data_get($creator, 'id');

            return in_array($creatorId, array_map('strval', $authUser->bookmarks), true);
        }

        return false;
    }

    private function resolveIsFollowing(mixed $creator, ?User $authUser): bool
    {
        if (! $creator) {
            return false;
        }

        if ($creator instanceof User && $creator->getAttribute('is_following') !== null) {
            return (bool) $creator->getAttribute('is_following');
        }

        if ($authUser && Schema::hasTable('user_follows')) {
            $creatorId = (string) data_get($creator, 'id');
            $authUserId = (string) $authUser->id;

            if ($creatorId !== '' && $creatorId !== $authUserId) {
                return UserFollow::query()
                    ->where('follower_id', $authUserId)
                    ->where('following_id', $creatorId)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            }
        }

        return false;
    }

    /**
     * @return array{is_connected: bool, connection_status: ?string, is_requested: bool, can_send_connection_request: bool}
     */
    private function resolveConnectionInfo(mixed $creator, ?User $authUser): array
    {
        if (! $creator) {
            return [
                'is_connected' => false,
                'connection_status' => null,
                'is_requested' => false,
                'can_send_connection_request' => false,
            ];
        }

        if ($creator instanceof User && $creator->getAttribute('is_connected') !== null) {
            return [
                'is_connected' => (bool) $creator->getAttribute('is_connected'),
                'connection_status' => $creator->getAttribute('connection_status'),
                'is_requested' => (bool) $creator->getAttribute('is_requested'),
                'can_send_connection_request' => (bool) ($creator->getAttribute('can_send_connection_request') ?? false),
            ];
        }

        if (! $authUser || ! Schema::hasTable('connections')) {
            return [
                'is_connected' => false,
                'connection_status' => null,
                'is_requested' => false,
                'can_send_connection_request' => $authUser !== null,
            ];
        }

        $authUserId = (string) $authUser->id;
        $targetId = (string) data_get($creator, 'id');

        if ($authUserId === $targetId) {
            return [
                'is_connected' => false,
                'connection_status' => 'self',
                'is_requested' => false,
                'can_send_connection_request' => false,
            ];
        }

        $connection = Connection::query()
            ->where(function ($q) use ($authUserId, $targetId) {
                $q->where('requester_id', $authUserId)->where('addressee_id', $targetId);
            })
            ->orWhere(function ($q) use ($authUserId, $targetId) {
                $q->where('addressee_id', $authUserId)->where('requester_id', $targetId);
            })
            ->first();

        if (! $connection) {
            return [
                'is_connected' => false,
                'connection_status' => null,
                'is_requested' => false,
                'can_send_connection_request' => true,
            ];
        }

        $isConnected = (bool) $connection->is_approved;
        $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
        $connectionStatus = $isConnected
            ? 'connected'
            : ($isRequested ? 'pending_sent' : 'pending_received');

        return [
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => false,
        ];
    }
}
