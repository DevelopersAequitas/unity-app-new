<?php

declare(strict_types=1);

namespace App\Services\Peers;

use App\Models\City;
use App\Models\Connection;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\ActivityUserFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TopPeersService
{
    /**
     * Get top peers by business deals.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTopBusinessDeals(array $filters, ?User $authUser = null): array
    {
        $limit = $this->resolveLimit($filters);
        $type = strtolower((string) ($filters['type'] ?? 'given'));
        $sortBy = strtolower((string) ($filters['sort_by'] ?? 'count'));

        $subquery = $this->buildBusinessDealsAggregation($filters, $type);

        $results = $this->fetchTopAggregatedPeers(
            $subquery,
            $filters,
            $sortBy === 'amount' ? 'total_amount' : 'deals_count',
            $sortBy === 'amount' ? 'deals_count' : 'total_amount',
            $limit
        );

        $peerDataMap = [];
        foreach ($results as $index => $row) {
            $peerDataMap[(string) $row->user_id] = [
                'rank' => $index + 1,
                'score' => (int) $row->deals_count,
                'deals_count' => (int) $row->deals_count,
                'total_amount' => round((float) ($row->total_amount ?? 0), 2),
            ];
        }

        $formattedPeers = $this->buildFormattedPeers($peerDataMap, $authUser);

        $myRank = $this->calculateMyRankForDeals($subquery, $filters, $authUser, $sortBy);

        return [
            'activity_type' => 'business_deals',
            'total' => count($formattedPeers),
            'peers' => $formattedPeers,
            'my_rank' => $myRank,
        ];
    }

    /**
     * Get top peers by P2P meetings.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTopP2pMeetings(array $filters, ?User $authUser = null): array
    {
        $limit = $this->resolveLimit($filters);
        $type = strtolower((string) ($filters['type'] ?? 'all'));

        $subquery = $this->buildP2pMeetingsAggregation($filters, $type);

        $results = $this->fetchTopAggregatedPeers(
            $subquery,
            $filters,
            'meetings_count',
            null,
            $limit
        );

        $peerDataMap = [];
        foreach ($results as $index => $row) {
            $peerDataMap[(string) $row->user_id] = [
                'rank' => $index + 1,
                'score' => (int) $row->meetings_count,
                'meetings_count' => (int) $row->meetings_count,
            ];
        }

        $formattedPeers = $this->buildFormattedPeers($peerDataMap, $authUser);

        $myRank = $this->calculateMyRankGeneric($subquery, $filters, $authUser, 'meetings_count');

        return [
            'activity_type' => 'p2p_meetings',
            'total' => count($formattedPeers),
            'peers' => $formattedPeers,
            'my_rank' => $myRank,
        ];
    }

    /**
     * Get top peers by testimonials.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTopTestimonials(array $filters, ?User $authUser = null): array
    {
        $limit = $this->resolveLimit($filters);
        $type = strtolower((string) ($filters['type'] ?? 'given'));

        $subquery = $this->buildTestimonialsAggregation($filters, $type);

        $results = $this->fetchTopAggregatedPeers(
            $subquery,
            $filters,
            'testimonials_count',
            'avg_rating',
            $limit
        );

        $peerDataMap = [];
        foreach ($results as $index => $row) {
            $peerDataMap[(string) $row->user_id] = [
                'rank' => $index + 1,
                'score' => (int) $row->testimonials_count,
                'testimonials_count' => (int) $row->testimonials_count,
                'avg_rating' => round((float) ($row->avg_rating ?? 0), 2),
            ];
        }

        $formattedPeers = $this->buildFormattedPeers($peerDataMap, $authUser);

        $myRank = $this->calculateMyRankGeneric($subquery, $filters, $authUser, 'testimonials_count');

        return [
            'activity_type' => 'testimonials',
            'total' => count($formattedPeers),
            'peers' => $formattedPeers,
            'my_rank' => $myRank,
        ];
    }

    /**
     * Get top peers by referrals.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTopReferrals(array $filters, ?User $authUser = null): array
    {
        $limit = $this->resolveLimit($filters);
        $type = strtolower((string) ($filters['type'] ?? 'given'));

        $subquery = $this->buildReferralsAggregation($filters, $type);

        $results = $this->fetchTopAggregatedPeers(
            $subquery,
            $filters,
            'referrals_count',
            null,
            $limit
        );

        $peerDataMap = [];
        foreach ($results as $index => $row) {
            $peerDataMap[(string) $row->user_id] = [
                'rank' => $index + 1,
                'score' => (int) $row->referrals_count,
                'referrals_count' => (int) $row->referrals_count,
            ];
        }

        $formattedPeers = $this->buildFormattedPeers($peerDataMap, $authUser);

        $myRank = $this->calculateMyRankGeneric($subquery, $filters, $authUser, 'referrals_count');

        return [
            'activity_type' => 'referrals',
            'total' => count($formattedPeers),
            'peers' => $formattedPeers,
            'my_rank' => $myRank,
        ];
    }

    private function resolveLimit(array $filters): int
    {
        $limit = (int) ($filters['limit'] ?? 10);

        return min(max($limit, 1), 100);
    }

    private function buildBusinessDealsAggregation(array $filters, string $type): QueryBuilder
    {
        $fromDate = $filters['from_date'] ?? $filters['from_at'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to_at'] ?? null;

        $base = DB::table('business_deals as bd');
        $this->applyUndeletedConditions($base, 'business_deals', 'bd');
        $this->applyDateFilter($base, 'business_deals', 'bd', $fromDate, $toDate, 'deal_date');

        if ($type === 'received') {
            return $base->select('bd.to_user_id as user_id')
                ->selectRaw('count(*) as deals_count')
                ->selectRaw('coalesce(sum(bd.deal_amount), 0) as total_amount')
                ->groupBy('bd.to_user_id');
        }

        if ($type === 'all') {
            $given = clone $base;
            $given->select('bd.from_user_id as user_id', 'bd.deal_amount');

            $received = DB::table('business_deals as bd');
            $this->applyUndeletedConditions($received, 'business_deals', 'bd');
            $this->applyDateFilter($received, 'business_deals', 'bd', $fromDate, $toDate, 'deal_date');
            $received->select('bd.to_user_id as user_id', 'bd.deal_amount');

            $union = $given->unionAll($received);

            return DB::query()
                ->fromSub($union, 'combined_deals')
                ->select('user_id')
                ->selectRaw('count(*) as deals_count')
                ->selectRaw('coalesce(sum(deal_amount), 0) as total_amount')
                ->groupBy('user_id');
        }

        // Default 'given'
        return $base->select('bd.from_user_id as user_id')
            ->selectRaw('count(*) as deals_count')
            ->selectRaw('coalesce(sum(bd.deal_amount), 0) as total_amount')
            ->groupBy('bd.from_user_id');
    }

    private function buildP2pMeetingsAggregation(array $filters, string $type): QueryBuilder
    {
        $fromDate = $filters['from_date'] ?? $filters['from_at'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to_at'] ?? null;

        $base = DB::table('p2p_meetings as pm');
        $this->applyUndeletedConditions($base, 'p2p_meetings', 'pm');
        $this->applyDateFilter($base, 'p2p_meetings', 'pm', $fromDate, $toDate, 'meeting_date');

        if ($type === 'initiated') {
            return $base->select('pm.initiator_user_id as user_id')
                ->selectRaw('count(*) as meetings_count')
                ->groupBy('pm.initiator_user_id');
        }

        if ($type === 'attended') {
            return $base->select('pm.peer_user_id as user_id')
                ->selectRaw('count(*) as meetings_count')
                ->groupBy('pm.peer_user_id');
        }

        // Default 'all'
        $initiator = clone $base;
        $initiator->select('pm.initiator_user_id as user_id');

        $attended = DB::table('p2p_meetings as pm');
        $this->applyUndeletedConditions($attended, 'p2p_meetings', 'pm');
        $this->applyDateFilter($attended, 'p2p_meetings', 'pm', $fromDate, $toDate, 'meeting_date');
        $attended->select('pm.peer_user_id as user_id');

        $union = $initiator->unionAll($attended);

        return DB::query()
            ->fromSub($union, 'combined_meetings')
            ->select('user_id')
            ->selectRaw('count(*) as meetings_count')
            ->groupBy('user_id');
    }

    private function buildTestimonialsAggregation(array $filters, string $type): QueryBuilder
    {
        $fromDate = $filters['from_date'] ?? $filters['from_at'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to_at'] ?? null;

        $base = DB::table('testimonials as tm');
        $this->applyUndeletedConditions($base, 'testimonials', 'tm');
        $this->applyDateFilter($base, 'testimonials', 'tm', $fromDate, $toDate, 'created_at');

        if ($type === 'received') {
            return $base->select('tm.to_user_id as user_id')
                ->selectRaw('count(*) as testimonials_count')
                ->selectRaw('coalesce(avg(tm.rating), 0) as avg_rating')
                ->groupBy('tm.to_user_id');
        }

        if ($type === 'all') {
            $given = clone $base;
            $given->select('tm.from_user_id as user_id', 'tm.rating');

            $received = DB::table('testimonials as tm');
            $this->applyUndeletedConditions($received, 'testimonials', 'tm');
            $this->applyDateFilter($received, 'testimonials', 'tm', $fromDate, $toDate, 'created_at');
            $received->select('tm.to_user_id as user_id', 'tm.rating');

            $union = $given->unionAll($received);

            return DB::query()
                ->fromSub($union, 'combined_testimonials')
                ->select('user_id')
                ->selectRaw('count(*) as testimonials_count')
                ->selectRaw('coalesce(avg(rating), 0) as avg_rating')
                ->groupBy('user_id');
        }

        // Default 'given'
        return $base->select('tm.from_user_id as user_id')
            ->selectRaw('count(*) as testimonials_count')
            ->selectRaw('coalesce(avg(tm.rating), 0) as avg_rating')
            ->groupBy('tm.from_user_id');
    }

    private function buildReferralsAggregation(array $filters, string $type): QueryBuilder
    {
        $fromDate = $filters['from_date'] ?? $filters['from_at'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to_at'] ?? null;

        $base = DB::table('referrals as ref');
        $this->applyUndeletedConditions($base, 'referrals', 'ref');
        $this->applyDateFilter($base, 'referrals', 'ref', $fromDate, $toDate, 'referral_date');

        if ($type === 'received') {
            return $base->select('ref.to_user_id as user_id')
                ->selectRaw('count(*) as referrals_count')
                ->groupBy('ref.to_user_id');
        }

        if ($type === 'all') {
            $given = clone $base;
            $given->select('ref.from_user_id as user_id');

            $received = DB::table('referrals as ref');
            $this->applyUndeletedConditions($received, 'referrals', 'ref');
            $this->applyDateFilter($received, 'referrals', 'ref', $fromDate, $toDate, 'referral_date');
            $received->select('ref.to_user_id as user_id');

            $union = $given->unionAll($received);

            return DB::query()
                ->fromSub($union, 'combined_referrals')
                ->select('user_id')
                ->selectRaw('count(*) as referrals_count')
                ->groupBy('user_id');
        }

        // Default 'given'
        return $base->select('ref.from_user_id as user_id')
            ->selectRaw('count(*) as referrals_count')
            ->groupBy('ref.from_user_id');
    }

    private function fetchTopAggregatedPeers(
        QueryBuilder $subquery,
        array $filters,
        string $primarySortColumn,
        ?string $secondarySortColumn,
        int $limit
    ): Collection {
        $query = DB::query()
            ->fromSub($subquery, 'agg')
            ->join('users as u', 'u.id', '=', 'agg.user_id')
            ->select('agg.*');

        ActivityUserFilter::applyToUserQuery($query, 'u.id', 'u');
        $this->applyCircleAndSearchFilters($query, $filters);

        $query->orderByDesc("agg.{$primarySortColumn}");
        if ($secondarySortColumn) {
            $query->orderByDesc("agg.{$secondarySortColumn}");
        }
        $query->orderBy('agg.user_id');

        return $query->limit($limit)->get();
    }

    private function applyUndeletedConditions(QueryBuilder $query, string $table, string $alias): void
    {
        if (Schema::hasColumn($table, 'is_deleted')) {
            $query->where("{$alias}.is_deleted", false);
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$alias}.deleted_at");
        }
    }

    private function applyDateFilter(
        QueryBuilder $query,
        string $table,
        string $alias,
        mixed $fromDate,
        mixed $toDate,
        string $preferredDateCol
    ): void {
        $column = Schema::hasColumn($table, $preferredDateCol) ? $preferredDateCol : 'created_at';

        if ($fromDate) {
            try {
                $fromCarbon = Carbon::parse($fromDate)->startOfDay();
                $query->where("{$alias}.{$column}", '>=', $fromCarbon);
            } catch (\Throwable) {
            }
        }

        if ($toDate) {
            try {
                $toCarbon = Carbon::parse($toDate)->endOfDay();
                $query->where("{$alias}.{$column}", '<=', $toCarbon);
            } catch (\Throwable) {
            }
        }
    }

    private function applyCircleAndSearchFilters(QueryBuilder $query, array $filters): void
    {
        $circleId = trim((string) ($filters['circle_id'] ?? ''));
        if ($circleId !== '' && strtolower($circleId) !== 'any' && Schema::hasTable('circle_members')) {
            $query->whereExists(function (QueryBuilder $sub) use ($circleId): void {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'u.id')
                    ->where('cm_filter.circle_id', $circleId)
                    ->whereNull('cm_filter.deleted_at');

                if (Schema::hasColumn('circle_members', 'status')) {
                    $sub->where('cm_filter.status', 'approved');
                }
            });
        }

        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (QueryBuilder $q) use ($like): void {
                $q->where('u.display_name', 'LIKE', $like)
                    ->orWhere('u.first_name', 'LIKE', $like)
                    ->orWhere('u.last_name', 'LIKE', $like);

                if (Schema::hasColumn('users', 'company_name')) {
                    $q->orWhere('u.company_name', 'LIKE', $like);
                }

                if (Schema::hasColumn('users', 'city')) {
                    $q->orWhere('u.city', 'LIKE', $like);
                }
            });
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $peerDataMap
     * @return array<int, array<string, mixed>>
     */
    private function buildFormattedPeers(array $peerDataMap, ?User $authUser): array
    {
        if (empty($peerDataMap)) {
            return [];
        }

        $userIds = array_keys($peerDataMap);

        $query = User::query()
            ->whereIn('id', $userIds);

        if (Schema::hasColumn('users', 'city_id') && Schema::hasTable('cities')) {
            $query->with(['city:id,name']);
        }

        $users = $query->get()->keyBy(fn (User $user): string => (string) $user->id);

        $this->attachUserInteractionAttributes($authUser, $users);

        $formatted = [];
        foreach ($peerDataMap as $userId => $meta) {
            $user = $users->get($userId);
            if (! $user) {
                continue;
            }

            $profilePhotoFileId = $user->profile_photo_file_id ?? null;
            $profilePhotoUrl = $profilePhotoFileId
                ? rtrim((string) config('app.url'), '/').'/api/v1/files/'.$profilePhotoFileId
                : ($user->profile_photo_url ?? null);

            $city = $this->resolveCityName($user);

            $peerArray = array_merge([
                'rank' => $meta['rank'],
                'id' => (string) $user->id,
                'display_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'first_name' => (string) ($user->first_name ?? ''),
                'last_name' => (string) ($user->last_name ?? ''),
                'company_name' => (string) ($user->company_name ?? ''),
                'designation' => (string) ($user->designation ?? ''),
                'category' => (string) ($user->business_type ?? ''),
                'city' => $city,
                'profile_photo' => [
                    'file_id' => $profilePhotoFileId,
                    'url' => $profilePhotoUrl,
                ],
                'profile_photo_url' => $profilePhotoUrl,
                'is_connected' => (bool) ($user->getAttribute('is_connected') ?? false),
                'connection_status' => $user->getAttribute('connection_status'),
                'is_requested' => (bool) ($user->getAttribute('is_requested') ?? false),
                'can_send_connection_request' => (bool) ($user->getAttribute('can_send_connection_request') ?? true),
                'is_following' => (bool) ($user->getAttribute('is_following') ?? false),
                'is_bookmark' => (bool) ($user->getAttribute('is_bookmark') ?? false),
                'is_pro' => (bool) ($user->getAttribute('is_pro') ?? false),
                'is_verified' => (bool) ($user->getAttribute('is_verified') ?? false),
            ], $meta);

            $formatted[] = $peerArray;
        }

        return $formatted;
    }

    private function calculateMyRankForDeals(
        QueryBuilder $subquery,
        array $filters,
        ?User $authUser,
        string $sortBy
    ): ?array {
        if (! $authUser) {
            return null;
        }

        $authUserId = (string) $authUser->id;

        $userRow = DB::query()
            ->fromSub($subquery, 'agg')
            ->where('user_id', $authUserId)
            ->first();

        $dealsCount = (int) ($userRow->deals_count ?? 0);
        $totalAmount = round((float) ($userRow->total_amount ?? 0), 2);

        if ($dealsCount <= 0 && $totalAmount <= 0) {
            return [
                'rank' => null,
                'user_id' => $authUserId,
                'score' => 0,
                'deals_count' => 0,
                'total_amount' => 0.0,
            ];
        }

        $higherQuery = DB::query()
            ->fromSub($subquery, 'agg')
            ->join('users as u', 'u.id', '=', 'agg.user_id');

        ActivityUserFilter::applyToUserQuery($higherQuery, 'u.id', 'u');
        $this->applyCircleAndSearchFilters($higherQuery, $filters);

        if ($sortBy === 'amount') {
            $higherCount = $higherQuery->where(function (QueryBuilder $q) use ($totalAmount, $dealsCount): void {
                $q->where('agg.total_amount', '>', $totalAmount)
                    ->orWhere(function (QueryBuilder $inner) use ($totalAmount, $dealsCount): void {
                        $inner->where('agg.total_amount', '=', $totalAmount)
                            ->where('agg.deals_count', '>', $dealsCount);
                    });
            })->count();
        } else {
            $higherCount = $higherQuery->where(function (QueryBuilder $q) use ($dealsCount, $totalAmount): void {
                $q->where('agg.deals_count', '>', $dealsCount)
                    ->orWhere(function (QueryBuilder $inner) use ($dealsCount, $totalAmount): void {
                        $inner->where('agg.deals_count', '=', $dealsCount)
                            ->where('agg.total_amount', '>', $totalAmount);
                    });
            })->count();
        }

        return [
            'rank' => $higherCount + 1,
            'user_id' => $authUserId,
            'score' => $dealsCount,
            'deals_count' => $dealsCount,
            'total_amount' => $totalAmount,
        ];
    }

    private function calculateMyRankGeneric(
        QueryBuilder $subquery,
        array $filters,
        ?User $authUser,
        string $metricColumn
    ): ?array {
        if (! $authUser) {
            return null;
        }

        $authUserId = (string) $authUser->id;

        $userRow = DB::query()
            ->fromSub($subquery, 'agg')
            ->where('user_id', $authUserId)
            ->first();

        $count = (int) ($userRow->{$metricColumn} ?? 0);

        if ($count <= 0) {
            return [
                'rank' => null,
                'user_id' => $authUserId,
                'score' => 0,
                $metricColumn => 0,
            ];
        }

        $higherQuery = DB::query()
            ->fromSub($subquery, 'agg')
            ->join('users as u', 'u.id', '=', 'agg.user_id');

        ActivityUserFilter::applyToUserQuery($higherQuery, 'u.id', 'u');
        $this->applyCircleAndSearchFilters($higherQuery, $filters);

        $higherCount = $higherQuery->where("agg.{$metricColumn}", '>', $count)->count();

        return [
            'rank' => $higherCount + 1,
            'user_id' => $authUserId,
            'score' => $count,
            $metricColumn => $count,
        ];
    }

    private function attachUserInteractionAttributes(?User $authUser, Collection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        if (! $authUser) {
            $users->each(function (User $user): void {
                $user->setAttribute('is_bookmark', false);
                $user->setAttribute('is_following', false);
                $user->setAttribute('is_pro', (bool) ($user->is_verified ?? false));
                $user->setAttribute('is_verified', (bool) ($user->is_verified ?? false));
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', null);
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', true);
            });

            return;
        }

        $authUserId = (string) $authUser->id;
        $userIds = $users->pluck('id')->map(fn ($id): string => (string) $id)->all();

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

        $users->each(function (User $user) use ($connections, $authUserId, $followedUserIds, $bookmarkIds): void {
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

        return null;
    }
}
