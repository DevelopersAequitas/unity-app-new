<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\User;
use App\Models\UserCircleRequest;
use App\Models\UserCircleRequestNominee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves real-time status of nominees:
 * 1. Has the nominee joined Unity? (registered user in the system)
 * 2. Has the nominee joined the Circle? (member of the created circle)
 */
final class UserCircleNomineeStatusResolver
{
    /**
     * Resolve all nominees for a given UserCircleRequest with their Unity and Circle join statuses.
     *
     * @param  UserCircleRequest  $request
     * @param  Collection<int, UserCircleRequestNominee>|null  $nominees
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveNominees(UserCircleRequest $request, ?Collection $nominees = null): Collection
    {
        $nomineeModels = $nominees ?? $request->nominees()->with(['category', 'subcategory'])->get();

        if ($nomineeModels->isEmpty()) {
            return collect();
        }

        $nomineeModels->loadMissing(['category', 'subcategory']);

        // 1. Gather all lookup candidates
        $explicitUserIds = $nomineeModels->pluck('nominee_user_id')->filter()->unique()->values()->all();
        $emails = $nomineeModels->pluck('email')->filter()->map(fn ($e) => strtolower(trim((string) $e)))->unique()->values()->all();
        $phones = $nomineeModels->pluck('phone')->filter()->map(fn ($p) => trim((string) $p))->unique()->values()->all();

        // 2. Batch fetch matching users
        $usersQuery = User::query()->whereNull('deleted_at');

        $usersQuery->where(function ($q) use ($explicitUserIds, $emails, $phones): void {
            $hasCondition = false;

            if (! empty($explicitUserIds)) {
                $q->whereIn('id', $explicitUserIds);
                $hasCondition = true;
            }

            if (! empty($emails)) {
                if ($hasCondition) {
                    $q->orWhereIn(DB::raw('LOWER(email)'), $emails);
                } else {
                    $q->whereIn(DB::raw('LOWER(email)'), $emails);
                    $hasCondition = true;
                }
            }

            if (! empty($phones)) {
                if ($hasCondition) {
                    $q->orWhereIn('phone', $phones);
                } else {
                    $q->whereIn('phone', $phones);
                    $hasCondition = true;
                }

                // Also check last 10 digits for each phone to match international formats
                foreach ($phones as $p) {
                    $digits = substr((string) preg_replace('/\D+/', '', $p), -10);
                    if (strlen($digits) === 10) {
                        $q->orWhere('phone', 'like', "%{$digits}");
                        if (Schema::hasColumn('users', 'secondary_mobile')) {
                            $q->orWhere('secondary_mobile', 'like', "%{$digits}");
                        }
                    }
                }
            }
        });

        $userColumns = ['id', 'first_name', 'last_name', 'display_name', 'email', 'phone', 'created_at', 'status'];
        if (Schema::hasColumn('users', 'profile_photo_url')) {
            $userColumns[] = 'profile_photo_url';
        }
        $matchedUsers = $usersQuery->get($userColumns);

        // Build indexes for instant memory lookup
        $userById = [];
        $userByEmail = [];
        $userByPhoneDigits = [];

        foreach ($matchedUsers as $u) {
            $userById[$u->id] = $u;

            if ($u->email) {
                $userByEmail[strtolower(trim($u->email))] = $u;
            }

            if ($u->phone) {
                $digits = substr((string) preg_replace('/\D+/', '', $u->phone), -10);
                if (strlen($digits) === 10) {
                    $userByPhoneDigits[$digits] = $u;
                }
            }
        }

        // 3. Batch fetch Circle memberships if circle was already created
        $createdCircleId = $request->created_circle_id;
        $circleMembersByUserId = [];
        $circleJoinRequestsByUserId = [];

        if ($createdCircleId && ! empty($matchedUsers)) {
            $allUserIds = $matchedUsers->pluck('id')->all();

            $members = CircleMember::query()
                ->where('circle_id', $createdCircleId)
                ->whereIn('user_id', $allUserIds)
                ->whereNull('deleted_at')
                ->get(['id', 'circle_id', 'user_id', 'role', 'status', 'joined_at', 'created_at']);

            foreach ($members as $m) {
                $circleMembersByUserId[(string) $m->user_id] = $m;
            }

            if (Schema::hasTable('circle_join_requests')) {
                $joinReqs = CircleJoinRequest::query()
                    ->where('circle_id', $createdCircleId)
                    ->whereIn('user_id', $allUserIds)
                    ->get(['id', 'circle_id', 'user_id', 'status', 'requested_at', 'created_at']);

                foreach ($joinReqs as $jr) {
                    $circleJoinRequestsByUserId[(string) $jr->user_id] = $jr;
                }
            }
        }

        // 4. Resolve each nominee
        $unlinkedNomineeUpdates = [];

        $resolved = $nomineeModels->map(function (UserCircleRequestNominee $nominee) use (
            $createdCircleId,
            $userById,
            $userByEmail,
            $userByPhoneDigits,
            $circleMembersByUserId,
            $circleJoinRequestsByUserId,
            &$unlinkedNomineeUpdates
        ): array {
            // Find user match
            $matchedUser = null;

            if ($nominee->nominee_user_id && isset($userById[$nominee->nominee_user_id])) {
                $matchedUser = $userById[$nominee->nominee_user_id];
            }

            if (! $matchedUser && $nominee->email) {
                $cleanEmail = strtolower(trim($nominee->email));
                if (isset($userByEmail[$cleanEmail])) {
                    $matchedUser = $userByEmail[$cleanEmail];
                }
            }

            if (! $matchedUser && $nominee->phone) {
                $digits = substr((string) preg_replace('/\D+/', '', $nominee->phone), -10);
                if (strlen($digits) === 10 && isset($userByPhoneDigits[$digits])) {
                    $matchedUser = $userByPhoneDigits[$digits];
                }
            }

            // If we found a user for an unlinked nominee, schedule auto-link
            if ($matchedUser && empty($nominee->nominee_user_id)) {
                $unlinkedNomineeUpdates[$nominee->id] = $matchedUser->id;
            }

            $hasJoinedUnity = ($matchedUser !== null);
            $hasJoinedCircle = false;
            $circleJoinStatus = $createdCircleId ? 'not_joined' : 'circle_not_created';
            $circleJoinedAt = null;
            $circleRole = null;

            if ($matchedUser && $createdCircleId) {
                $userId = (string) $matchedUser->id;

                if (isset($circleMembersByUserId[$userId])) {
                    $member = $circleMembersByUserId[$userId];
                    $mStatus = strtolower((string) ($member->status ?? 'approved'));

                    if (in_array($mStatus, ['approved', 'active'], true) || empty($mStatus)) {
                        $hasJoinedCircle = true;
                        $circleJoinStatus = 'joined';
                    } else {
                        $circleJoinStatus = $mStatus; // e.g. pending
                    }

                    $circleJoinedAt = $member->joined_at?->toISOString() ?? $member->created_at?->toISOString();
                    $circleRole = (string) ($member->role ?? 'member');
                } elseif (isset($circleJoinRequestsByUserId[$userId])) {
                    $jr = $circleJoinRequestsByUserId[$userId];
                    $jrStatus = strtolower((string) $jr->status);

                    if (in_array($jrStatus, [CircleJoinRequest::STATUS_CIRCLE_MEMBER, CircleJoinRequest::STATUS_PAID], true)) {
                        $hasJoinedCircle = true;
                        $circleJoinStatus = 'joined';
                    } elseif (in_array($jrStatus, [
                        CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                        CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                        CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
                    ], true)) {
                        $circleJoinStatus = 'request_sent';
                    } else {
                        $circleJoinStatus = $jrStatus;
                    }
                }
            }

            $userName = $matchedUser
                ? (trim(($matchedUser->first_name ?? '').' '.($matchedUser->last_name ?? '')) ?: $matchedUser->display_name ?: $nominee->name)
                : $nominee->name;

            $peerPhone = $nominee->phone ?: $matchedUser?->phone;
            $peerEmail = $nominee->email ?: $matchedUser?->email;
            $peerId = $matchedUser?->id ? (string) $matchedUser->id : ($nominee->nominee_user_id ? (string) $nominee->nominee_user_id : null);
            $peerSource = $nominee->source ?? ($matchedUser ? 'app' : 'manual');

            return [
                'id' => (string) $nominee->id,
                'source' => $peerSource,
                'peer_id' => $peerId,
                'user_id' => $peerId,
                'nominee_user_id' => $peerId,
                'name' => $nominee->name ?: $userName,
                'phone' => $peerPhone,
                'email' => $peerEmail,
                'business_name' => $nominee->business_name,
                'category_id' => $nominee->category_id,
                'category' => $nominee->category ? [
                    'id' => $nominee->category->id,
                    'name' => $nominee->category->name,
                    'slug' => $nominee->category->slug,
                ] : null,
                'subcategory_id' => $nominee->subcategory_id,
                'subcategory' => $nominee->subcategory ? [
                    'id' => $nominee->subcategory->id,
                    'name' => $nominee->subcategory->name,
                    'slug' => $nominee->subcategory->slug,
                ] : null,
                'interest_level' => $nominee->interest_level,
                'is_complete' => (! empty($nominee->name) && ! empty($peerPhone) && ! empty($nominee->business_name) && ! empty($nominee->category_id)),

                // ── 1. Unity Join Status ──
                'has_joined_unity' => $hasJoinedUnity,
                'unity_join_status' => $hasJoinedUnity ? 'joined' : 'not_joined',
                'unity_joined_at' => $matchedUser?->created_at?->toISOString(),
                'unity_user' => $matchedUser ? [
                    'id' => (string) $matchedUser->id,
                    'name' => $userName,
                    'email' => $matchedUser->email,
                    'phone' => $matchedUser->phone,
                    'status' => $matchedUser->status,
                    'profile_photo_url' => $matchedUser->profile_photo_url ?? null,
                ] : null,

                // ── 2. Circle Join Status ──
                'has_joined_circle' => $hasJoinedCircle,
                'circle_join_status' => $circleJoinStatus,
                'circle_joined_at' => $circleJoinedAt,
                'circle_role' => $circleRole,
            ];
        });

        // Persist newly discovered user links in background (so DB stays updated)
        if (! empty($unlinkedNomineeUpdates)) {
            foreach ($unlinkedNomineeUpdates as $nomId => $uId) {
                UserCircleRequestNominee::where('id', $nomId)->update(['nominee_user_id' => $uId]);
            }
        }

        return $resolved;
    }

    /**
     * Get aggregate statistics on nominees for a circle request.
     *
     * @param  UserCircleRequest  $request
     * @param  Collection<int, array<string, mixed>>|null  $resolvedNominees
     * @return array<string, int>
     */
    public function getSummary(UserCircleRequest $request, ?Collection $resolvedNominees = null): array
    {
        $nominees = $resolvedNominees ?? $this->resolveNominees($request);

        $total = $nominees->count();
        $joinedUnity = $nominees->where('has_joined_unity', true)->count();
        $joinedCircle = $nominees->where('has_joined_circle', true)->count();
        $pendingCircle = $nominees->where('circle_join_status', 'request_sent')->count();

        return [
            'total_recommended_peers' => $total,
            'total_nominees'          => $total, // alias
            'joined_unity_count'      => $joinedUnity,
            'not_joined_unity_count'  => max(0, $total - $joinedUnity),
            'joined_circle_count'     => $joinedCircle,
            'pending_circle_count'    => $pendingCircle,
            'not_joined_circle_count' => max(0, $total - $joinedCircle),
        ];
    }
}

