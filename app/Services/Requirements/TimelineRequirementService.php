<?php

declare(strict_types=1);

namespace App\Services\Requirements;

use App\Models\CircleCategoryLevel4;
use App\Models\Connection;
use App\Models\Requirement;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TimelineRequirementService
{
    public function getOpenRequirements(Request $request): LengthAwarePaginator
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        $postsSubquery = DB::table('posts')
            ->where('source_type', '=', 'requirement')
            ->where('is_deleted', '=', false);

        if ($isPgsql) {
            $postsSubquery->selectRaw('DISTINCT ON (source_id) source_id, id as post_id')
                ->orderBy('source_id')
                ->orderByDesc('created_at');
        } else {
            $postsSubquery->selectRaw('source_id, MAX(id) as post_id')
                ->groupBy('source_id');
        }

        $userRelations = ['user.level4Category'];
        if (Schema::hasTable('circle_members')) {
            $userRelations[] = 'user.circleMembers.level4Category';
        }
        if (Schema::hasTable('circle_categories')) {
            $userRelations[] = 'user.businessCategory';
        }

        $query = Requirement::query()
            ->select('requirements.*')
            ->selectRaw('rp.post_id as post_id')
            ->with($userRelations)
            ->leftJoinSub(
                $postsSubquery,
                'rp',
                'rp.source_id',
                '=',
                'requirements.id'
            )
            ->where('requirements.status', '=', 'open')
            ->whereNull('requirements.deleted_at')
            ->orderByDesc('requirements.created_at');

        $paginated = $query->paginate($perPage);

        $authUser = auth('sanctum')->user() ?: ($request->user() instanceof User ? $request->user() : null);
        $this->attachSocialAttributes($authUser instanceof User ? $authUser : null, $paginated->items());

        return $paginated;
    }

    /**
     * Batch attach connection, follow, bookmark, and level4 category details onto requirement creators.
     *
     * @param  array<int, Requirement>|Collection<int, Requirement>  $requirements
     */
    public function attachSocialAttributes(?User $authUser, mixed $requirements): void
    {
        $requirementCollection = $requirements instanceof Collection ? $requirements : collect($requirements);
        $users = $requirementCollection->pluck('user')->filter()->unique('id')->values();

        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $authUserId = $authUser ? (string) $authUser->id : null;

        $connections = collect();
        if ($authUser && Schema::hasTable('connections') && ! empty($userIds)) {
            $sent = Connection::query()
                ->where('requester_id', $authUserId)
                ->whereIn('addressee_id', $userIds);

            $connections = Connection::query()
                ->where('addressee_id', $authUserId)
                ->whereIn('requester_id', $userIds)
                ->union($sent)
                ->get()
                ->keyBy(function (Connection $connection) use ($authUserId): string {
                    return (string) ((string) $connection->requester_id === $authUserId
                        ? $connection->addressee_id
                        : $connection->requester_id);
                });
        }

        $followedUserIds = [];
        if ($authUser && Schema::hasTable('user_follows') && ! empty($userIds)) {
            $followedUserIds = UserFollow::query()
                ->where('follower_id', $authUserId)
                ->whereIn('following_id', $userIds)
                ->whereIn('status', ['accepted', 'pending'])
                ->pluck('following_id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        }

        $bookmarks = ($authUser && is_array($authUser->bookmarks))
            ? array_map('strval', $authUser->bookmarks)
            : [];

        $users->each(function (User $user) use ($connections, $authUserId, $followedUserIds, $bookmarks): void {
            $targetId = (string) $user->id;
            $isSelf = $authUserId !== null && $authUserId === $targetId;

            // Level 4 Category
            $user->setAttribute('level4_category', $this->resolveLevel4Category($user));

            // Bookmark
            $user->setAttribute('is_bookmark', in_array($targetId, $bookmarks, true));

            // Follow
            $user->setAttribute('is_following', in_array($targetId, $followedUserIds, true));

            // Verified
            $rawVerified = $user->is_verified ?? null;
            $isVerified = ($rawVerified !== null)
                ? (bool) $rawVerified
                : (method_exists($user, 'isPaidMember') ? (bool) $user->isPaidMember() : false);
            $user->setAttribute('is_verified', $isVerified);

            // Pro
            $isPro = false;
            if ($rawVerified !== null && (bool) $rawVerified) {
                $isPro = true;
            } elseif (method_exists($user, 'isPaidMember')) {
                $isPro = (bool) $user->isPaidMember();
            } else {
                $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
                $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
            }
            $user->setAttribute('is_pro', $isPro);

            // Connection states
            if ($isSelf) {
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', 'self');
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', false);
            } else {
                $connection = $connections->get($targetId);
                if (! $connection) {
                    $user->setAttribute('is_connected', false);
                    $user->setAttribute('connection_status', null);
                    $user->setAttribute('is_requested', false);
                    $user->setAttribute('can_send_connection_request', $authUserId !== null);
                } else {
                    $isConnected = (bool) $connection->is_approved;
                    $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
                    $connectionStatus = $isConnected
                        ? 'connected'
                        : ($isRequested ? 'pending_sent' : 'pending_received');

                    $user->setAttribute('is_connected', $isConnected);
                    $user->setAttribute('connection_status', $connectionStatus);
                    $user->setAttribute('is_requested', $isRequested);
                    $user->setAttribute('can_send_connection_request', false);
                }
            }
        });
    }

    public function resolveLevel4Category(User $user): ?string
    {
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

        if (Schema::hasTable('joined_circle_categories') && Schema::hasColumn('joined_circle_categories', 'level_4_category_id') && class_exists(CircleCategoryLevel4::class) && Schema::hasTable('circle_category_level4')) {
            try {
                $level4Id = DB::table('joined_circle_categories')
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

        if ($user->relationLoaded('businessCategory') && $user->businessCategory) {
            return $user->businessCategory->name ?? null;
        }

        return null;
    }
}
