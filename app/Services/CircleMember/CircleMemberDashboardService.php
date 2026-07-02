<?php

declare(strict_types=1);

namespace App\Services\CircleMember;

use App\Models\AdminUser;
use App\Models\BusinessDeal;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\CoinClaimRequest;
use App\Models\CoinLedger;
use App\Models\Connection;
use App\Models\EventRegistrationRequest;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\VisitorRegistration;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Support\Facades\Schema;

class CircleMemberDashboardService
{
    /**
     * Get aggregated dashboard statistics and recent data for circle-scoped admin users.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(AdminUser $admin): array
    {
        $user = AdminAccess::resolveAppUser($admin);
        if (! $user) {
            return [
                'user' => null,
                'totalPeers' => 0,
                'userCoins' => 0,
                'totalCircleCoins' => 0,
                'joinedCircles' => collect(),
                'recentPeers' => collect(),
                'recentTransactions' => collect(),
                'pendingCounts' => [
                    'circleJoin' => 0,
                    'coinClaims' => 0,
                    'visitorRegistrations' => 0,
                    'eventJoining' => 0,
                    'total' => 0,
                ],
            ];
        }

        $allowedCircleIds = AdminAccess::allowedCircleIds($admin);
        $allowedUserIds = AdminAccess::allowedUserIds($admin);

        // Joined Circles & Role
        $joinedCircles = CircleMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->with(['circle' => function ($query) {
                $query->orderBy('name')
                    ->with(['founder', 'director', 'ded']);
            }])
            ->get();

        // Scoped Statistics
        $totalPeers = 0;
        if (! empty($allowedCircleIds)) {
            $totalPeers = CircleMember::query()
                ->whereIn('circle_id', $allowedCircleIds)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->distinct('user_id')
                ->count('user_id');
        }

        $userCoins = $user->coins_balance ?? 0;

        $totalCircleCoins = 0;
        if (! empty($allowedUserIds)) {
            $totalCircleCoins = User::query()
                ->whereIn('id', $allowedUserIds)
                ->sum('coins_balance');
        }

        // Recent Peers Joined in their Circles
        $recentPeers = collect();
        if (! empty($allowedCircleIds)) {
            $recentPeers = CircleMember::query()
                ->whereIn('circle_id', $allowedCircleIds)
                ->where('status', 'approved')
                ->where('user_id', '!=', $user->id)
                ->whereNull('deleted_at')
                ->with(['user', 'circle', 'roleModel'])
                ->latest('joined_at')
                ->limit(5)
                ->get();
        }

        // Recent Coin Transactions in their Circles
        $recentTransactions = collect();
        if (! empty($allowedUserIds)) {
            $recentTransactions = CoinLedger::query()
                ->whereIn('user_id', $allowedUserIds)
                ->latest('created_at')
                ->limit(5)
                ->with('user')
                ->get();
        }

        // Pending Request Counts
        $circleJoinCount = 0;
        if (! empty($allowedCircleIds)) {
            $circleJoinCount = CircleJoinRequest::visibleToAdminUser($admin)
                ->pending()
                ->count();
        }

        $coinClaimsCount = 0;
        $claimTable = (new CoinClaimRequest)->getTable();
        if (Schema::hasTable($claimTable)) {
            $coinClaimsQuery = CoinClaimRequest::query()->where('status', 'pending');
            AdminCircleScope::applyToActivityQuery($coinClaimsQuery, $admin, "{$claimTable}.user_id", null);
            $coinClaimsCount = $coinClaimsQuery->count();
        }

        $visitorRegistrationsCount = 0;
        $visitorTable = (new VisitorRegistration)->getTable();
        if (Schema::hasTable($visitorTable)) {
            $visitorQuery = VisitorRegistration::query()->where('status', 'pending');
            AdminCircleScope::applyToActivityQuery($visitorQuery, $admin, "{$visitorTable}.user_id", null);
            $visitorRegistrationsCount = $visitorQuery->count();
        }

        $eventJoiningCount = 0;
        $eventReqTable = (new EventRegistrationRequest)->getTable();
        if (Schema::hasTable($eventReqTable)) {
            $eventQuery = EventRegistrationRequest::query()->where('status', 'pending');
            $eventQuery->where(function ($scopeQuery) use ($admin, $eventReqTable) {
                $scopeQuery->whereHas('event', function ($eventQuery) use ($admin): void {
                    AdminCircleScope::applyToEventsQuery($eventQuery, $admin);
                })->orWhere(function ($userScope) use ($admin, $eventReqTable): void {
                    AdminCircleScope::applyToActivityQuery($userScope, $admin, "{$eventReqTable}.user_id", null);
                });
            });
            $eventJoiningCount = $eventQuery->count();
        }

        $totalPending = $circleJoinCount + $coinClaimsCount + $visitorRegistrationsCount + $eventJoiningCount;

        $activityCounts = [
            'totalLivesImpacted' => 0,
            'totalCoinsEarned' => 0,
            'businessDealsCount' => 0,
            'businessDealsAmount' => 0,
            'referralsPassed' => 0,
            'connectionsMade' => 0,
            'p2pMeetings' => 0,
            'requirementsPosted' => 0,
            'testimonialsExchanged' => 0,
            'visitors' => 0,
            'circleLeftMembers' => 0,
            'circleJoinedMembers' => 0,
        ];

        if (! empty($allowedUserIds)) {
            $activityCounts['totalLivesImpacted'] = (int) User::query()
                ->whereIn('id', $allowedUserIds)
                ->sum('life_impacted_count');

            $activityCounts['totalCoinsEarned'] = (int) CoinLedger::query()
                ->whereIn('user_id', $allowedUserIds)
                ->where('amount', '>', 0)
                ->sum('amount');

            $activityCounts['businessDealsCount'] = BusinessDeal::query()
                ->where('is_deleted', false)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($allowedUserIds): void {
                    $q->whereIn('from_user_id', $allowedUserIds)
                        ->orWhereIn('to_user_id', $allowedUserIds);
                })
                ->count();

            $activityCounts['businessDealsAmount'] = (float) BusinessDeal::query()
                ->where('is_deleted', false)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($allowedUserIds): void {
                    $q->whereIn('from_user_id', $allowedUserIds)
                        ->orWhereIn('to_user_id', $allowedUserIds);
                })
                ->sum('deal_amount');

            $activityCounts['referralsPassed'] = Referral::query()
                ->where('is_deleted', false)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($allowedUserIds): void {
                    $q->whereIn('from_user_id', $allowedUserIds)
                        ->orWhereIn('to_user_id', $allowedUserIds);
                })
                ->count();

            $activityCounts['connectionsMade'] = 0;
            if (Schema::hasTable('connections')) {
                $activityCounts['connectionsMade'] = Connection::query()
                    ->where('is_approved', true)
                    ->where(function ($q) use ($allowedUserIds): void {
                        $q->whereIn('requester_id', $allowedUserIds)
                            ->orWhereIn('addressee_id', $allowedUserIds);
                    })
                    ->count();
            }

            $activityCounts['p2pMeetings'] = P2pMeeting::query()
                ->where('is_deleted', false)
                ->whereNull('deleted_at')
                ->whereDate('meeting_date', '<', now()->toDateString())
                ->where(function ($q) use ($allowedUserIds): void {
                    $q->whereIn('initiator_user_id', $allowedUserIds)
                        ->orWhereIn('peer_user_id', $allowedUserIds);
                })
                ->count();

            $activityCounts['requirementsPosted'] = Requirement::query()
                ->whereNull('deleted_at')
                ->whereIn('user_id', $allowedUserIds)
                ->count();

            $activityCounts['testimonialsExchanged'] = Testimonial::query()
                ->where('is_deleted', false)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($allowedUserIds): void {
                    $q->whereIn('from_user_id', $allowedUserIds)
                        ->orWhereIn('to_user_id', $allowedUserIds);
                })
                ->count();

            $activityCounts['visitors'] = VisitorRegistration::query()
                ->whereIn('user_id', $allowedUserIds)
                ->count();
        }

        $leftMembers = collect();
        if (! empty($allowedCircleIds)) {
            $activityCounts['circleLeftMembers'] = CircleMember::withTrashed()
                ->whereIn('circle_id', $allowedCircleIds)
                ->whereNotNull('left_at')
                ->where('left_at', '>=', now()->subDays(30))
                ->count();

            $activityCounts['circleJoinedMembers'] = CircleMember::query()
                ->whereIn('circle_id', $allowedCircleIds)
                ->where('status', 'approved')
                ->whereNull('left_at')
                ->where('joined_at', '>=', now()->subDays(30))
                ->count();

            $leftMembers = CircleMember::withTrashed()
                ->whereIn('circle_id', $allowedCircleIds)
                ->whereNotNull('left_at')
                ->where('left_at', '>=', now()->subDays(30))
                ->with(['user', 'circle'])
                ->get();
        }

        return [
            'user' => $user,
            'totalPeers' => $totalPeers,
            'userCoins' => $userCoins,
            'totalCircleCoins' => $totalCircleCoins,
            'joinedCircles' => $joinedCircles,
            'recentPeers' => $recentPeers,
            'recentTransactions' => $recentTransactions,
            'pendingCounts' => [
                'circleJoin' => $circleJoinCount,
                'coinClaims' => $coinClaimsCount,
                'visitorRegistrations' => $visitorRegistrationsCount,
                'eventJoining' => $eventJoiningCount,
                'total' => $totalPending,
            ],
            'activityCounts' => $activityCounts,
            'leftMembers' => $leftMembers,
        ];
    }
}
