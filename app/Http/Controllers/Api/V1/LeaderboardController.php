<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Connection;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaderboardController extends Controller
{
    public function coins(): JsonResponse
    {
        $members = $this->baseLeaderboardQuery()
            ->orderByRaw('COALESCE(coins_balance, 0) DESC')
            ->orderBy('display_name', 'asc')
            ->limit(20)
            ->get();

        $loggedInUser = request()->user();
        $this->attachUserInteractionAttributes($loggedInUser, $members);

        $myRank = null;

        if ($loggedInUser) {
            $allIds = $this->baseLeaderboardQuery()
                ->orderByRaw('COALESCE(coins_balance, 0) DESC')
                ->orderBy('display_name', 'asc')
                ->pluck('id')
                ->toArray();

            $rankIndex = array_search($loggedInUser->id, $allIds);
            if ($rankIndex !== false) {
                $userRank = $rankIndex + 1;
                if ($userRank > 20) {
                    $userModel = $this->baseLeaderboardQuery()->find($loggedInUser->id);
                    if ($userModel) {
                        $this->attachUserInteractionAttributes($loggedInUser, collect([$userModel]));
                        $transformed = $this->transformMembers(collect([$userModel]))->first();
                        $transformed['rank'] = $userRank;
                        $myRank = $transformed;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'leaderboard_type' => 'coins',
                'total' => $members->count(),
                'members' => $this->transformMembers($members),
                'my_rank' => $myRank,
            ],
        ]);
    }

    public function impacts(): JsonResponse
    {
        $members = $this->baseLeaderboardQuery()
            ->orderByRaw('COALESCE(life_impacted_count, 0) DESC')
            ->orderBy('display_name', 'asc')
            ->limit(20)
            ->get();

        $loggedInUser = request()->user();
        $this->attachUserInteractionAttributes($loggedInUser, $members);

        $myRank = null;

        if ($loggedInUser) {
            $allIds = $this->baseLeaderboardQuery()
                ->orderByRaw('COALESCE(life_impacted_count, 0) DESC')
                ->orderBy('display_name', 'asc')
                ->pluck('id')
                ->toArray();

            $rankIndex = array_search($loggedInUser->id, $allIds);
            if ($rankIndex !== false) {
                $userRank = $rankIndex + 1;
                if ($userRank > 20) {
                    $userModel = $this->baseLeaderboardQuery()->find($loggedInUser->id);
                    if ($userModel) {
                        $this->attachUserInteractionAttributes($loggedInUser, collect([$userModel]));
                        $transformed = $this->transformMembers(collect([$userModel]))->first();
                        $transformed['rank'] = $userRank;
                        $myRank = $transformed;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'leaderboard_type' => 'impacts',
                'total' => $members->count(),
                'members' => $this->transformMembers($members),
                'my_rank' => $myRank,
            ],
        ]);
    }

    private function baseLeaderboardQuery(): Builder
    {
        $selectColumns = [
            'id',
            'display_name',
            'first_name',
            'last_name',
            'company_name',
            'business_type',
            'profile_photo_file_id',
            'coins_balance',
            'life_impacted_count',
            'coin_medal_rank',
            'coin_milestone_title',
            'coin_milestone_meaning',
            'contribution_award_name',
            'contribution_award_recognition',
        ];

        $optionalColumns = [
            'city_id',
            'city',
            'city_name',
            'business_city',
            'city_of_residence',
            'membership_status',
            'membership_ends_at',
            'membership_expiry',
            'membership_end_date',
            'is_verified',
        ];

        foreach ($optionalColumns as $col) {
            if (Schema::hasColumn('users', $col)) {
                $selectColumns[] = $col;
            }
        }

        $query = User::query()
            ->select($selectColumns)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('users', 'status')) {
            $query->where('status', 'active');
        }

        if (Schema::hasColumn('users', 'membership_status')) {
            $query->whereNotNull('membership_status')
                ->where('membership_status', '!=', '')
                ->whereNotIn(
                    DB::raw("LOWER(TRIM(REPLACE(membership_status, ' ', '_')))"),
                    ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free', 'free_trial']
                );
        }

        if (Schema::hasColumn('users', 'membership_ends_at')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('membership_ends_at')
                    ->orWhere('membership_ends_at', '>=', now());
            });
        }

        if (Schema::hasColumn('users', 'membership_expiry')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('membership_expiry')
                    ->orWhere('membership_expiry', '>=', now());
            });
        }

        if (Schema::hasColumn('users', 'membership_end_date')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('membership_end_date')
                    ->orWhere('membership_end_date', '>=', now()->toDateString());
            });
        }

        if (Schema::hasTable('user_tag_assignments') && Schema::hasTable('user_tags')) {
            $query->whereDoesntHave('tags', function (Builder $tagQuery): void {
                $tagQuery->where('user_tags.slug', 'team_member')
                    ->where('user_tags.is_active', true);
            });
        }

        if (Schema::hasColumn('users', 'city_id') && Schema::hasTable('cities')) {
            $query->with(['city:id,name']);
        }

        return $query;
    }

    private function attachUserInteractionAttributes(?User $authUser, mixed $users): void
    {
        $userCollection = $users instanceof Collection ? $users : collect($users);

        if ($userCollection->isEmpty()) {
            return;
        }

        if (! $authUser) {
            $userCollection->each(function (User $user): void {
                $user->setAttribute('is_bookmark', false);
                $user->setAttribute('is_following', false);

                $rawVerified = $user->is_verified ?? null;
                if ($rawVerified !== null && (bool) $rawVerified) {
                    $isPro = true;
                } elseif (method_exists($user, 'isPaidMember')) {
                    $isPro = (bool) $user->isPaidMember();
                } else {
                    $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
                    $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
                }

                $user->setAttribute('is_pro', $isPro);
                $user->setAttribute('is_verified', (bool) ($user->is_verified ?? false));
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', null);
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', true);
            });

            return;
        }

        $authUserId = (string) $authUser->id;
        $userIds = $userCollection->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $bookmarks = $authUser->bookmarks ?? [];
        if (! is_array($bookmarks)) {
            $bookmarks = [];
        }
        $bookmarkIds = array_map('strval', $bookmarks);

        $connections = collect();
        if (Schema::hasTable('connections') && ! empty($userIds)) {
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
        if (Schema::hasTable('user_follows') && ! empty($userIds)) {
            $followedUserIds = UserFollow::query()
                ->where('follower_id', $authUserId)
                ->whereIn('following_id', $userIds)
                ->whereIn('status', ['accepted', 'pending'])
                ->pluck('following_id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        }

        $userCollection->each(function (User $user) use ($connections, $authUserId, $followedUserIds, $bookmarkIds): void {
            $isSelf = (string) $user->id === $authUserId;

            $user->setAttribute('is_bookmark', in_array((string) $user->id, $bookmarkIds, true));
            $user->setAttribute('is_following', in_array((string) $user->id, $followedUserIds, true));

            $rawVerified = $user->is_verified ?? null;
            if ($rawVerified !== null && (bool) $rawVerified) {
                $isPro = true;
            } elseif (method_exists($user, 'isPaidMember')) {
                $isPro = (bool) $user->isPaidMember();
            } else {
                $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));
                $isPro = $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
            }

            $user->setAttribute('is_pro', $isPro);
            $user->setAttribute('is_verified', (bool) ($user->is_verified ?? false));

            $connection = $connections->get((string) $user->id);

            if (! $connection) {
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', $isSelf ? 'self' : null);
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', ! $isSelf);

                return;
            }

            $isConnected = (bool) $connection->is_approved;
            $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
            $status = $isConnected
                ? 'connected'
                : ($isRequested ? 'pending_sent' : 'pending_received');

            $user->setAttribute('is_connected', $isConnected);
            $user->setAttribute('connection_status', $status);
            $user->setAttribute('is_requested', $isRequested);
            $user->setAttribute('can_send_connection_request', false);
        });
    }

    private function resolveCityName(User $member): ?string
    {
        if ($member->relationLoaded('city')) {
            $cityRelation = $member->getRelation('city');
            if ($cityRelation instanceof City && filled($cityRelation->name)) {
                return trim((string) $cityRelation->name);
            }
        }

        if ($member->relationLoaded('cityRelation')) {
            $cityRelation = $member->getRelation('cityRelation');
            if ($cityRelation instanceof City && filled($cityRelation->name)) {
                return trim((string) $cityRelation->name);
            }
        }

        $city = $member->getAttribute('city');
        if (is_array($city) && ! empty($city['name'])) {
            return trim((string) $city['name']);
        }
        if (is_object($city) && ! empty($city->name)) {
            return trim((string) $city->name);
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

        $cityName = $member->getAttribute('city_name');
        if (is_string($cityName) && trim($cityName) !== '') {
            return trim($cityName);
        }

        $businessCity = $member->getAttribute('business_city');
        if (is_string($businessCity) && trim($businessCity) !== '') {
            return trim($businessCity);
        }

        $cityOfResidence = $member->getAttribute('city_of_residence');
        if (is_string($cityOfResidence) && trim($cityOfResidence) !== '') {
            return trim($cityOfResidence);
        }

        return null;
    }

    private function transformMembers($members)
    {
        return $members->values()->map(function (User $member, int $index): array {
            $profilePhotoFileId = $member->profile_photo_file_id;

            return [
                'rank' => $index + 1,
                'id' => $member->id,
                'display_name' => $member->display_name,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'company_name' => $member->company_name,
                'category' => $member->business_type,
                'city' => $this->resolveCityName($member),
                'is_bookmark' => (bool) ($member->getAttribute('is_bookmark') ?? false),
                'is_following' => (bool) ($member->getAttribute('is_following') ?? false),
                'is_verified' => (bool) ($member->getAttribute('is_verified') ?? false),
                'is_pro' => (bool) ($member->getAttribute('is_pro') ?? false),
                'is_connected' => (bool) ($member->getAttribute('is_connected') ?? false),
                'connection_status' => $member->getAttribute('connection_status'),
                'is_requested' => (bool) ($member->getAttribute('is_requested') ?? false),
                'can_send_connection_request' => (bool) ($member->getAttribute('can_send_connection_request') ?? true),
                'profile_photo' => [
                    'file_id' => $profilePhotoFileId,
                    'url' => $profilePhotoFileId ? rtrim((string) config('app.url'), '/').'/api/v1/files/'.$profilePhotoFileId : null,
                ],
                'coins_balance' => (int) ($member->coins_balance ?? 0),
                'life_impacted_count' => (int) ($member->life_impacted_count ?? 0),
                'coin_medal_rank' => $member->coin_medal_rank,
                'coin_milestone_title' => $member->coin_milestone_title,
                'coin_milestone_meaning' => $member->coin_milestone_meaning,
                'contribution_award_name' => $member->contribution_award_name,
                'contribution_award_recognition' => $member->contribution_award_recognition,
            ];
        });
    }
}
