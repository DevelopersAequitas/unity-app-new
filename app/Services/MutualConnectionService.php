<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        $targetConnectionIds = $this->acceptedConnectionPeerIdsSubquery((string) $targetUser->id);

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
            ->whereIn('users.id', $targetConnectionIds)
            ->whereNotIn('users.id', [(string) $authUser->id, (string) $targetUser->id])
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
            ->when(Schema::hasTable('peer_blocks'), function (Builder $query) use ($authUser, $targetUser): void {
                $excludedIds = DB::table('peer_blocks')
                    ->select('blocked_user_id as user_id')
                    ->whereIn('blocker_user_id', [(string) $authUser->id, (string) $targetUser->id])
                    ->union(
                        DB::table('peer_blocks')
                            ->select('blocker_user_id as user_id')
                            ->whereIn('blocked_user_id', [(string) $authUser->id, (string) $targetUser->id])
                    );

                $query->whereNotIn('users.id', $excludedIds);
            })
            ->distinct()
            ->orderBy('sort_name', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Build a subquery of accepted peer IDs for the given user ID.
     */
    private function acceptedConnectionPeerIdsSubquery(string $userId): \Illuminate\Database\Query\Builder
    {
        return DB::query()
            ->fromSub(function ($query) use ($userId): void {
                $query->from('connections')
                    ->select('addressee_id as user_id')
                    ->where('requester_id', $userId)
                    ->where('is_approved', true)
                    ->union(
                        DB::table('connections')
                            ->select('requester_id as user_id')
                            ->where('addressee_id', $userId)
                            ->where('is_approved', true)
                    );
            }, 'accepted_connection_peers')
            ->select('user_id');
    }
}
