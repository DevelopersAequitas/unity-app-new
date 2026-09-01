<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MutualConnectionService
{
    /**
     * Fetch users connected to both the authenticated user and target user.
     *
     * @param  User  $authUser  Authenticated user.
     * @param  User  $targetUser  Target user to compare connections against.
     * @param  int  $perPage  Number of users per page.
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(User $authUser, User $targetUser, int $perPage = 20): LengthAwarePaginator
    {
        $authUserId = (string) $authUser->id;
        $targetUserId = (string) $targetUser->id;

        $authConnectionIds = $this->getAcceptedConnectionIds($authUserId);
        $targetConnectionIds = $this->getAcceptedConnectionIds($targetUserId);

        // 1. Calculate common/intersection connection IDs
        $commonIds = array_values(array_intersect($authConnectionIds, $targetConnectionIds));

        // 2. Exclude authenticated user and target user
        $finalMutualIds = array_values(array_diff($commonIds, [$authUserId, $targetUserId]));

        // Log required debug information
        Log::info('Mutual Connections Calculation Debug', [
            'authenticated_user_id' => $authUserId,
            'target_user_id' => $targetUserId,
            'authenticated_user_connection_ids' => $authConnectionIds,
            'target_user_connection_ids' => $targetConnectionIds,
            'final_mutual_connection_ids' => $finalMutualIds,
        ]);

        if (empty($finalMutualIds)) {
            /** @var Paginator<int, User> */
            return new Paginator([], 0, $perPage, 1, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $query = User::query()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'users.company_name',
                'users.designation',
                'users.profile_photo_file_id',
                'users.profile_photo_url',
                'users.city_id',
                'users.city',
                'users.status',
                'users.membership_status',
                'users.deleted_at',
            ])
            ->selectRaw("COALESCE(NULLIF(users.display_name, ''), TRIM(COALESCE(users.first_name, '') || ' ' || COALESCE(users.last_name, ''))) AS sort_name")
            ->with('city:id,name')
            ->whereIn('users.id', $finalMutualIds)
            ->whereNull('users.deleted_at')
            ->when(Schema::hasColumn('users', 'gdpr_deleted_at'), function (Builder $query): void {
                $query->whereNull('users.gdpr_deleted_at');
            })
            ->when(Schema::hasColumn('users', 'status'), function (Builder $query): void {
                $query->where(function (Builder $statusQuery): void {
                    $statusQuery->whereNull('users.status')->orWhere('users.status', 'active');
                });
            })
            ->when(Schema::hasColumn('users', 'membership_status'), function (Builder $query): void {
                $query->where(function (Builder $membershipQuery): void {
                    $membershipQuery->whereNull('users.membership_status')->orWhere('users.membership_status', '!=', 'suspended');
                });
            })
            ->when(Schema::hasTable('peer_blocks'), function (Builder $query) use ($authUserId, $targetUserId): void {
                $excludedIds = DB::table('peer_blocks')
                    ->select('blocked_user_id as user_id')
                    ->whereIn('blocker_user_id', [$authUserId, $targetUserId])
                    ->union(
                        DB::table('peer_blocks')
                            ->select('blocker_user_id as user_id')
                            ->whereIn('blocked_user_id', [$authUserId, $targetUserId])
                    );

                $query->whereNotIn('users.id', $excludedIds);
            })
            ->distinct()
            ->orderBy('sort_name', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Get array of accepted connection user IDs for a given user ID.
     *
     * @return array<int, string>
     */
    public function getAcceptedConnectionIds(string $userId): array
    {
        /** @var array<int, string> */
        $sentIds = DB::table('connections')
            ->where('requester_id', $userId)
            ->where(function ($q): void {
                $q->where('is_approved', true)->orWhere('is_approved', 1);
            })
            ->pluck('addressee_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        /** @var array<int, string> */
        $receivedIds = DB::table('connections')
            ->where('addressee_id', $userId)
            ->where(function ($q): void {
                $q->where('is_approved', true)->orWhere('is_approved', 1);
            })
            ->pluck('requester_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        return array_values(array_unique(array_merge($sentIds, $receivedIds)));
    }
}
