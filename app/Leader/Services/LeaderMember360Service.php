<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\ActivityCreative;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Post;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderMember360Service
{
    public function __construct(
        private readonly LeaderPeersService $peersService,
        private readonly LeaderPermissionService $permissionService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // SCOPE VALIDATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve and validate that the given member belongs to the leader's scope.
     * Returns the User model on success, or null if not found / out of scope.
     */
    public function resolveMember(string $memberId, User $leader): ?User
    {
        if (! Str::isUuid($memberId)) {
            return null;
        }

        $member = User::query()
            ->where('id', $memberId)
            ->whereNull('deleted_at')
            ->first();

        if (! $member) {
            return null;
        }

        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($leader);

        // null = global admin scope — can see all members
        if ($scopedCircleIds === null) {
            return $member;
        }

        // empty = leader has no authorized circles — deny
        if (empty($scopedCircleIds)) {
            return null;
        }

        // Check if the member belongs to any of the leader's authorized circles
        $inScope = DB::table('circle_members')
            ->where('user_id', $memberId)
            ->whereIn('circle_id', $scopedCircleIds)
            ->whereNull('deleted_at')
            ->exists();

        if ($inScope) {
            return $member;
        }

        // Also check active_circle_id column
        if (
            Schema::hasColumn('users', 'active_circle_id') &&
            ! empty($member->active_circle_id) &&
            in_array((string) $member->active_circle_id, $scopedCircleIds, true)
        ) {
            return $member;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEMBER 360° PROFILE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the full Member 360° profile with summary activity counts.
     *
     * @return array<string, mixed>
     */
    public function getMemberProfile(string $memberId, User $leader): array
    {
        $profile = $this->peersService->getPeer($memberId, $leader);

        if (empty($profile)) {
            return [];
        }

        $profile['summary'] = $this->buildSummary($memberId);

        return $profile;
    }

    /**
     * Build summary counts for all activity types.
     *
     * @return array<string, int>
     */
    private function buildSummary(string $memberId): array
    {
        $postsCount = DB::table('posts')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->count();

        $creativesCount = DB::table('activity_creatives')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->count();

        $badgesCount = DB::table('user_milestone_badges')
            ->where('user_id', $memberId)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->count();

        $testimonialsGiven = 0;
        $testimonialsReceived = 0;
        if (Schema::hasTable('testimonials')) {
            $testimonialsGiven = DB::table('testimonials')
                ->where('from_user_id', $memberId)
                ->whereNull('deleted_at')
                ->count();
            $testimonialsReceived = DB::table('testimonials')
                ->where('to_user_id', $memberId)
                ->whereNull('deleted_at')
                ->count();
        }

        $requirementsCount = 0;
        if (Schema::hasTable('requirements')) {
            $requirementsCount = DB::table('requirements')
                ->where('user_id', $memberId)
                ->whereNull('deleted_at')
                ->count();
        }

        $referralsGiven = 0;
        $referralsReceived = 0;
        if (Schema::hasTable('referrals')) {
            $referralsGiven = DB::table('referrals')
                ->where('from_user_id', $memberId)
                ->whereNull('deleted_at')
                ->count();
            $referralsReceived = DB::table('referrals')
                ->where('to_user_id', $memberId)
                ->whereNull('deleted_at')
                ->count();
        }

        $p2pMeetingsCount = 0;
        if (Schema::hasTable('p2p_meetings')) {
            $p2pMeetingsCount = DB::table('p2p_meetings')
                ->where(function ($q) use ($memberId): void {
                    $q->where('initiator_user_id', $memberId)
                        ->orWhere('peer_user_id', $memberId);
                })
                ->whereNull('deleted_at')
                ->count();
        }

        $businessDealsCount = 0;
        if (Schema::hasTable('business_deals')) {
            $businessDealsCount = DB::table('business_deals')
                ->where(function ($q) use ($memberId): void {
                    $q->where('from_user_id', $memberId)
                        ->orWhere('to_user_id', $memberId);
                })
                ->whereNull('deleted_at')
                ->count();
        }

        $lifeImpactsCount = 0;
        if (Schema::hasTable('impacts')) {
            $lifeImpactsCount = DB::table('impacts')
                ->where('user_id', $memberId)
                ->whereNull('deleted_at')
                ->count();
        }

        $eventRegistrationsCount = DB::table('event_registrations')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->count();

        $eventAttendanceCount = DB::table('event_registrations')
            ->where('user_id', $memberId)
            ->where('checkin_status', 'checked_in')
            ->whereNull('deleted_at')
            ->count();

        $coinsBalance = (int) DB::table('users')
            ->where('id', $memberId)
            ->value('coins_balance') ?? 0;

        return [
            'posts' => $postsCount,
            'creatives' => $creativesCount,
            'badges' => $badgesCount,
            'testimonials_given' => $testimonialsGiven,
            'testimonials_received' => $testimonialsReceived,
            'requirements' => $requirementsCount,
            'referrals_given' => $referralsGiven,
            'referrals_received' => $referralsReceived,
            'p2p_meetings' => $p2pMeetingsCount,
            'business_deals' => $businessDealsCount,
            'life_impacts' => $lifeImpactsCount,
            'event_registrations' => $eventRegistrationsCount,
            'event_attendance' => $eventAttendanceCount,
            'coins_balance' => $coinsBalance,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UNIFIED ACTIVITY FEED
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return a paginated unified chronological activity feed for a member.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getActivities(string $memberId, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? 20)));
        $activityType = isset($filters['activity_type']) && trim((string) $filters['activity_type']) !== ''
            ? trim((string) $filters['activity_type'])
            : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        // Map activityType filter to the specific sub-fetchers
        $types = match ($activityType) {
            'p2p_meeting' => ['p2p_meeting'],
            'referral_given' => ['referral_given'],
            'referral_received' => ['referral_received'],
            'referral' => ['referral_given', 'referral_received'],
            'deal_given' => ['deal_given'],
            'deal_received' => ['deal_received'],
            'deal_closed' => ['deal_closed'],
            'business_deal' => ['deal_given', 'deal_received'],
            'attendance' => ['attendance'],
            'coins' => ['coins'],
            'life_impact' => ['life_impact'],
            'testimonial' => ['testimonial'],
            'requirement' => ['requirement'],
            'post' => ['post'],
            'creative' => ['creative'],
            'badge' => ['badge'],
            'event_registration' => ['event_registration'],
            null => [
                'p2p_meeting',
                'referral_given',
                'referral_received',
                'deal_given',
                'deal_received',
                'attendance',
                'coins',
                'life_impact',
                'testimonial',
                'requirement',
                'post',
                'creative',
                'badge',
            ],
            default => [$activityType],
        };

        // Fetch buffer to allow proper cross-type chronological sorting and pagination
        $buffer = max(100, ($page * $perPage) + ($perPage * 3));
        $activities = [];

        foreach ($types as $type) {
            $items = $this->fetchActivityType(
                type: $type,
                memberId: $memberId,
                fromDate: $fromDate,
                toDate: $toDate,
                search: $search,
                limit: $buffer,
            );
            $activities = array_merge($activities, $items);
        }

        // Batch resolve counterpart user names
        $counterpartIds = [];
        foreach ($activities as $act) {
            if (! empty($act['_counterpart_user_id'])) {
                $counterpartIds[] = (string) $act['_counterpart_user_id'];
            }
        }
        $counterpartIds = array_values(array_unique(array_filter($counterpartIds)));

        $nameMap = [];
        if (! empty($counterpartIds)) {
            $users = DB::table('users')
                ->whereIn('id', $counterpartIds)
                ->select(['id', 'first_name', 'last_name', 'display_name'])
                ->get();

            foreach ($users as $u) {
                $displayName = trim((string) ($u->display_name ?? ''));
                if ($displayName !== '') {
                    $nameMap[$u->id] = $displayName;

                    continue;
                }
                $fullName = trim(trim((string) ($u->first_name ?? '')).' '.trim((string) ($u->last_name ?? '')));
                $nameMap[$u->id] = $fullName !== '' ? $fullName : (trim((string) ($u->first_name ?? '')) ?: 'Member');
            }
        }

        // Populate counterpart_name and default optional fields
        foreach ($activities as &$item) {
            if (array_key_exists('_counterpart_user_id', $item)) {
                $cid = $item['_counterpart_user_id'];
                $item['counterpart_name'] = $cid ? ($nameMap[$cid] ?? 'Member') : null;
                unset($item['_counterpart_user_id']);
            }
            if (! array_key_exists('counterpart_name', $item)) {
                $item['counterpart_name'] = null;
            }
            if (! array_key_exists('amount', $item)) {
                $item['amount'] = null;
            }
        }
        unset($item);

        // Sort all items by timestamp descending
        usort($activities, function (array $a, array $b): int {
            $tsA = $a['_sort_ts'] ?? 0;
            $tsB = $b['_sort_ts'] ?? 0;

            return $tsB <=> $tsA;
        });

        // Strip internal sorting key
        $activities = array_map(function (array $item): array {
            unset($item['_sort_ts']);

            return $item;
        }, $activities);

        $total = count($activities);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($activities, $offset, $perPage);

        return [
            'data' => array_values($items),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'limit' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * Fetch normalized activity items for a single activity type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchActivityType(
        string $type,
        string $memberId,
        ?string $fromDate,
        ?string $toDate,
        ?string $search,
        int $limit,
    ): array {
        return match ($type) {
            'p2p_meeting' => $this->fetchP2pMeetings($memberId, $fromDate, $toDate, $search, $limit),
            'referral_given' => $this->fetchReferrals($memberId, $fromDate, $toDate, $search, $limit, 'given'),
            'referral_received' => $this->fetchReferrals($memberId, $fromDate, $toDate, $search, $limit, 'received'),
            'referral' => $this->fetchReferrals($memberId, $fromDate, $toDate, $search, $limit, 'all'),
            'deal_given' => $this->fetchBusinessDeals($memberId, $fromDate, $toDate, $search, $limit, 'given'),
            'deal_received' => $this->fetchBusinessDeals($memberId, $fromDate, $toDate, $search, $limit, 'received'),
            'deal_closed' => $this->fetchBusinessDeals($memberId, $fromDate, $toDate, $search, $limit, 'closed'),
            'business_deal' => $this->fetchBusinessDeals($memberId, $fromDate, $toDate, $search, $limit, 'all'),
            'attendance' => $this->fetchAttendance($memberId, $fromDate, $toDate, $search, $limit),
            'coins' => $this->fetchCoins($memberId, $fromDate, $toDate, $search, $limit),
            'life_impact' => $this->fetchImpacts($memberId, $fromDate, $toDate, $search, $limit),
            'testimonial' => $this->fetchTestimonials($memberId, $fromDate, $toDate, $search, $limit),
            'requirement' => $this->fetchRequirements($memberId, $fromDate, $toDate, $search, $limit),
            'post' => $this->fetchPosts($memberId, $fromDate, $toDate, $search, $limit),
            'creative' => $this->fetchCreatives($memberId, $fromDate, $toDate, $search, $limit),
            'badge' => $this->fetchBadges($memberId, $fromDate, $toDate, $search, $limit),
            'event_registration' => $this->fetchEventRegistrations($memberId, $fromDate, $toDate, $search, $limit),
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchP2pMeetings(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('p2p_meetings')) {
            return [];
        }

        $q = DB::table('p2p_meetings')
            ->where(function ($sq) use ($memberId): void {
                $sq->where('initiator_user_id', $memberId)
                    ->orWhere('peer_user_id', $memberId);
            })
            ->whereNull('deleted_at');

        if (Schema::hasColumn('p2p_meetings', 'is_deleted')) {
            $q->where('is_deleted', false);
        }

        $dateCol = Schema::hasColumn('p2p_meetings', 'meeting_date') ? 'meeting_date' : 'created_at';
        $this->applyDateFilters($q, $dateCol, $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('remarks', 'like', "%{$search}%");
                if (Schema::hasColumn('p2p_meetings', 'meeting_place')) {
                    $sq->orWhere('meeting_place', 'like', "%{$search}%");
                }
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($memberId): array {
                $meetingDate = $row->meeting_date ? Carbon::parse($row->meeting_date) : ($row->created_at ? Carbon::parse($row->created_at) : now());
                $isoDate = $meetingDate->toIso8601String();
                $ts = $meetingDate->timestamp;
                $isInitiator = ((string) $row->initiator_user_id === $memberId);
                $counterpartUserId = $isInitiator ? (string) ($row->peer_user_id ?? '') : (string) ($row->initiator_user_id ?? '');

                $status = ! empty($row->status)
                    ? ucfirst((string) $row->status)
                    : ($meetingDate->isFuture() ? 'Confirmed' : 'Completed');

                $description = trim(($row->meeting_place ? "{$row->meeting_place}. " : '').($row->remarks ?? ''));
                if ($description === '') {
                    $description = 'One-on-one meeting';
                }

                $title = ! empty($row->title) ? (string) $row->title : '1-on-1 Intro Meeting';

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'p2p_meeting',
                    'title' => $title,
                    'counterpart_name' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'data' => [
                        'meeting_id' => (string) $row->id,
                        'meeting_date' => $row->meeting_date ? (string) $row->meeting_date : null,
                        'meeting_place' => (string) ($row->meeting_place ?? ''),
                        'remarks' => (string) ($row->remarks ?? ''),
                        'member_role' => $isInitiator ? 'initiator' : 'peer',
                        'initiator_user_id' => (string) ($row->initiator_user_id ?? ''),
                        'peer_user_id' => (string) ($row->peer_user_id ?? ''),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchReferrals(
        string $memberId,
        ?string $fromDate,
        ?string $toDate,
        ?string $search,
        int $limit,
        string $direction = 'all',
    ): array {
        if (! Schema::hasTable('referrals')) {
            return [];
        }

        $q = DB::table('referrals as r')
            ->whereNull('r.deleted_at');

        if (Schema::hasColumn('referrals', 'is_deleted')) {
            $q->where('r.is_deleted', false);
        }

        if ($direction === 'given') {
            $q->where('r.from_user_id', $memberId);
        } elseif ($direction === 'received') {
            $q->where('r.to_user_id', $memberId);
        } else {
            $q->where(function ($sq) use ($memberId): void {
                $sq->where('r.from_user_id', $memberId)
                    ->orWhere('r.to_user_id', $memberId);
            });
        }

        if (Schema::hasTable('referral_status')) {
            $q->leftJoin('referral_status as rs', 'rs.id', '=', 'r.status_id')
                ->select(['r.*', 'rs.name as status_name']);
        } else {
            $q->select(['r.*']);
        }

        $dateCol = Schema::hasColumn('referrals', 'referral_date') ? 'r.referral_date' : 'r.created_at';
        $this->applyDateFilters($q, $dateCol, $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('r.referral_of', 'like', "%{$search}%")
                    ->orWhere('r.remarks', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('r.created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($memberId): array {
                $isGiven = ((string) $row->from_user_id === $memberId);
                $activityType = $isGiven ? 'referral_given' : 'referral_received';
                $counterpartUserId = $isGiven ? (string) ($row->to_user_id ?? '') : (string) ($row->from_user_id ?? '');

                $refDate = $row->referral_date ? Carbon::parse($row->referral_date) : ($row->created_at ? Carbon::parse($row->created_at) : now());
                $isoDate = $refDate->toIso8601String();
                $ts = $refDate->timestamp;

                $rawStatus = (string) ($row->status_name ?? $row->status ?? 'Pending');
                $lowerStatus = strtolower($rawStatus);
                if (in_array($lowerStatus, ['converted', 'won', 'success'], true)) {
                    $status = 'Converted';
                } elseif (in_array($lowerStatus, ['contacted', 'in_progress', 'progress'], true)) {
                    $status = 'Contacted';
                } else {
                    $status = 'Pending';
                }

                $title = ! empty($row->referral_of)
                    ? (string) $row->referral_of
                    : (! empty($row->referral_type) ? ucwords(str_replace('_', ' ', (string) $row->referral_type)) : 'Referral');

                $descParts = [];
                if (! empty($row->remarks)) {
                    $descParts[] = (string) $row->remarks;
                }
                if (! empty($row->phone)) {
                    $descParts[] = 'Phone: '.$row->phone;
                }
                if (! empty($row->email)) {
                    $descParts[] = 'Email: '.$row->email;
                }
                $description = ! empty($descParts) ? implode(' | ', $descParts) : 'Referral details shared.';

                return [
                    'id' => (string) $row->id,
                    'activity_type' => $activityType,
                    'title' => $title,
                    'counterpart_name' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'data' => [
                        'referral_id' => (string) $row->id,
                        'referral_of' => (string) ($row->referral_of ?? ''),
                        'referral_type' => (string) ($row->referral_type ?? 'b2b_referral'),
                        'status' => $status,
                        'referral_date' => $row->referral_date ? (string) $row->referral_date : null,
                        'remarks' => (string) ($row->remarks ?? ''),
                        'member_role' => $isGiven ? 'giver' : 'receiver',
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchBusinessDeals(
        string $memberId,
        ?string $fromDate,
        ?string $toDate,
        ?string $search,
        int $limit,
        string $filter = 'all',
    ): array {
        if (! Schema::hasTable('business_deals')) {
            return [];
        }

        $q = DB::table('business_deals')
            ->whereNull('deleted_at');

        if (Schema::hasColumn('business_deals', 'is_deleted')) {
            $q->where('is_deleted', false);
        }

        if ($filter === 'given') {
            $q->where('from_user_id', $memberId);
        } elseif ($filter === 'received') {
            $q->where('to_user_id', $memberId);
        } else {
            $q->where(function ($sq) use ($memberId): void {
                $sq->where('from_user_id', $memberId)
                    ->orWhere('to_user_id', $memberId);
            });
        }

        $dateCol = Schema::hasColumn('business_deals', 'deal_date') ? 'deal_date' : 'created_at';
        $this->applyDateFilters($q, $dateCol, $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('comment', 'like', "%{$search}%");
                if (Schema::hasColumn('business_deals', 'business_type')) {
                    $sq->orWhere('business_type', 'like', "%{$search}%");
                }
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($memberId, $filter): array {
                $isGiver = ((string) ($row->from_user_id ?? '') === $memberId);
                $counterpartUserId = $isGiver ? (string) ($row->to_user_id ?? '') : (string) ($row->from_user_id ?? '');

                if ($filter === 'closed') {
                    $activityType = 'deal_closed';
                } elseif ($filter === 'given') {
                    $activityType = 'deal_given';
                } elseif ($filter === 'received') {
                    $activityType = 'deal_received';
                } else {
                    $activityType = $isGiver ? 'deal_given' : 'deal_received';
                }

                $dealDate = $row->deal_date ? Carbon::parse($row->deal_date) : ($row->created_at ? Carbon::parse($row->created_at) : now());
                $isoDate = $dealDate->toIso8601String();
                $ts = $dealDate->timestamp;

                $dealAmount = (float) ($row->deal_amount ?? 0);
                $formattedAmount = '₹ '.number_format($dealAmount, 0);

                $status = ! empty($row->status) ? ucfirst((string) $row->status) : 'Closed';

                $title = ! empty($row->business_type)
                    ? ucwords(str_replace('_', ' ', (string) $row->business_type))
                    : 'Business Deal';

                $description = (string) ($row->comment ?: 'Closed business transaction.');

                return [
                    'id' => (string) $row->id,
                    'activity_type' => $activityType,
                    'title' => $title,
                    'counterpart_name' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    'description' => $description,
                    'amount' => $formattedAmount,
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'data' => [
                        'deal_id' => (string) $row->id,
                        'deal_amount' => $dealAmount,
                        'business_type' => (string) ($row->business_type ?? ''),
                        'deal_date' => $row->deal_date ? (string) $row->deal_date : null,
                        'comment' => (string) ($row->comment ?? ''),
                        'member_role' => $isGiver ? 'giver' : 'receiver',
                        'from_user_id' => (string) ($row->from_user_id ?? ''),
                        'to_user_id' => (string) ($row->to_user_id ?? ''),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAttendance(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        $attendanceItems = [];

        // 1. Circle Meeting Attendance via attendance_records
        if (Schema::hasTable('attendance_records')) {
            $q = DB::table('attendance_records as ar')
                ->where('ar.member_user_id', $memberId);

            if (Schema::hasTable('circle_meetings')) {
                $q->leftJoin('circle_meetings as cm', 'cm.id', '=', 'ar.meeting_id')
                    ->select([
                        'ar.id',
                        'ar.status',
                        'ar.marked_at',
                        'ar.notes',
                        'ar.created_at',
                        'cm.meeting_date',
                        'cm.venue',
                        'cm.mode',
                        'cm.meeting_link',
                    ]);

                if (Schema::hasTable('circles')) {
                    $q->leftJoin('circles as c', 'c.id', '=', 'ar.circle_id')
                        ->addSelect('c.name as circle_name');
                }
            } else {
                $q->select(['ar.*']);
            }

            $dateCol = 'ar.created_at';
            $this->applyDateFilters($q, $dateCol, $fromDate, $toDate);

            if ($search) {
                $q->where(function ($sq) use ($search): void {
                    $sq->where('ar.notes', 'like', "%{$search}%");
                });
            }

            $rows = $q->orderByDesc('ar.created_at')->take($limit)->get();

            foreach ($rows as $row) {
                $rawDate = $row->marked_at ?: ($row->meeting_date ?: $row->created_at);
                $attDate = $rawDate ? Carbon::parse($rawDate) : now();
                $isoDate = $attDate->toIso8601String();

                $rawStatus = (string) ($row->status ?? 'Present');
                $status = ucfirst(strtolower($rawStatus));

                $circleName = ! empty($row->circle_name) ? (string) $row->circle_name : null;
                $title = $circleName ? "Weekly {$circleName} Meeting" : 'Weekly Circle Meeting';

                $description = ! empty($row->venue)
                    ? (string) $row->venue
                    : (! empty($row->meeting_link) ? 'Online via Meeting Link' : ($row->mode === 'online' ? 'Online via Zoom' : 'Circle Meeting'));

                $attendanceItems[] = [
                    'id' => (string) $row->id,
                    'activity_type' => 'attendance',
                    'title' => $title,
                    'counterpart_name' => null,
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'data' => [
                        'record_id' => (string) $row->id,
                        'status' => $status,
                        'notes' => (string) ($row->notes ?? ''),
                    ],
                    '_sort_ts' => $attDate->timestamp,
                ];
            }
        }

        // 2. Event Attendance via event_registrations
        if (Schema::hasTable('event_registrations')) {
            $eq = DB::table('event_registrations as er')
                ->leftJoin('events as e', 'e.id', '=', 'er.event_id')
                ->where('er.user_id', $memberId)
                ->whereNull('er.deleted_at')
                ->select([
                    'er.id',
                    'er.event_id',
                    'er.status as registration_status',
                    'er.checkin_status',
                    'er.checked_in_at',
                    'er.registered_at',
                    'er.created_at',
                    'e.title as event_title',
                    'e.start_at as event_start_at',
                    'e.is_virtual',
                    'e.location_text',
                ]);

            $this->applyDateFilters($eq, 'er.created_at', $fromDate, $toDate);

            if ($search) {
                $eq->where(function ($sq) use ($search): void {
                    $sq->where('e.title', 'like', "%{$search}%")
                        ->orWhere('e.location_text', 'like', "%{$search}%");
                });
            }

            $erRows = $eq->orderByDesc('er.created_at')->take($limit)->get();

            foreach ($erRows as $erRow) {
                $rawDate = $erRow->checked_in_at ?: ($erRow->event_start_at ?: ($erRow->registered_at ?: $erRow->created_at));
                $attDate = $rawDate ? Carbon::parse($rawDate) : now();
                $isoDate = $attDate->toIso8601String();

                if ($erRow->checkin_status === 'checked_in') {
                    $status = 'Present';
                } elseif ($erRow->checkin_status === 'late') {
                    $status = 'Late';
                } elseif ($erRow->checkin_status === 'excused') {
                    $status = 'Excused';
                } elseif ($erRow->registration_status === 'cancelled') {
                    $status = 'Cancelled';
                } elseif (! empty($erRow->event_start_at) && Carbon::parse($erRow->event_start_at)->isPast()) {
                    $status = 'Absent';
                } else {
                    $status = 'Present';
                }

                $title = ! empty($erRow->event_title) ? (string) $erRow->event_title : 'Circle Meeting';
                $description = (string) ($erRow->location_text ?: ($erRow->is_virtual ? 'Online via Zoom' : 'In-Person Meeting'));

                $attendanceItems[] = [
                    'id' => (string) $erRow->id,
                    'activity_type' => 'attendance',
                    'title' => $title,
                    'counterpart_name' => null,
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $erRow->created_at ? Carbon::parse($erRow->created_at)->toIso8601String() : $isoDate,
                    'data' => [
                        'registration_id' => (string) $erRow->id,
                        'event_id' => (string) ($erRow->event_id ?? ''),
                        'checkin_status' => (string) ($erRow->checkin_status ?? ''),
                    ],
                    '_sort_ts' => $attDate->timestamp,
                ];
            }
        }

        return $attendanceItems;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchCoins(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('coins_ledger')) {
            return [];
        }

        $q = DB::table('coins_ledger')
            ->where('user_id', $memberId);

        if (Schema::hasColumn('coins_ledger', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('remark', 'like', "%{$search}%");
                if (Schema::hasColumn('coins_ledger', 'reference')) {
                    $sq->orWhere('reference', 'like', "%{$search}%");
                }
            });
        }

        $hasTransId = Schema::hasColumn('coins_ledger', 'transaction_id');

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($hasTransId): array {
                $createdDate = $row->created_at ? Carbon::parse($row->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;

                $coinsDelta = (int) ($row->amount ?? $row->coins_delta ?? 0);
                $amountStr = $coinsDelta >= 0 ? "+{$coinsDelta}" : "{$coinsDelta}";
                $status = $coinsDelta >= 0 ? 'Earned' : 'Redeemed';

                $title = ! empty($row->remark) && mb_strlen((string) $row->remark) < 45
                    ? (string) $row->remark
                    : (! empty($row->reference) ? ucwords(str_replace(['_', '-'], ' ', (string) $row->reference)) : 'Coin Transaction');

                $description = (string) ($row->remark ?: ($coinsDelta >= 0 ? 'Coins earned for platform activity.' : 'Coins redeemed.'));
                $id = $hasTransId && ! empty($row->transaction_id) ? (string) $row->transaction_id : (string) ($row->id ?? Str::uuid());

                return [
                    'id' => $id,
                    'activity_type' => 'coins',
                    'title' => $title,
                    'counterpart_name' => null,
                    'description' => $description,
                    'amount' => $amountStr,
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'coins_delta' => $coinsDelta,
                        'balance_after' => $row->balance_after ?? null,
                        'remark' => (string) ($row->remark ?? ''),
                        'reference' => (string) ($row->reference ?? ''),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchImpacts(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('impacts')) {
            return [];
        }

        $q = DB::table('impacts')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('action', 'like', "%{$search}%")
                    ->orWhere('story_to_share', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $createdDate = $row->created_at ? Carbon::parse($row->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'life_impact',
                    'title' => 'Life Impact Created',
                    'counterpart_name' => null,
                    'description' => (string) ($row->action ?? 'Provided mentorship'),
                    'amount' => null,
                    'status' => ucfirst((string) ($row->status ?? 'approved')),
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'impact_id' => (string) $row->id,
                        'action' => (string) ($row->action ?? ''),
                        'story' => (string) ($row->story_to_share ?? ''),
                        'lives_impacted' => (int) ($row->life_impacted ?? 1),
                        'status' => ucfirst((string) ($row->status ?? 'approved')),
                        'impact_date' => $row->impact_date ? (string) $row->impact_date : null,
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchTestimonials(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('testimonials')) {
            return [];
        }

        $q = DB::table('testimonials')
            ->where(function ($sq) use ($memberId): void {
                $sq->where('from_user_id', $memberId)
                    ->orWhere('to_user_id', $memberId);
            })
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where('content', 'like', "%{$search}%");
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($memberId): array {
                $createdDate = $row->created_at ? Carbon::parse($row->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;
                $isAuthor = ((string) ($row->from_user_id ?? '') === $memberId);
                $counterpartUserId = $isAuthor ? (string) ($row->to_user_id ?? '') : (string) ($row->from_user_id ?? '');

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'testimonial',
                    'title' => $isAuthor ? 'Testimonial Given' : 'Testimonial Received',
                    'counterpart_name' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    'description' => (string) mb_substr((string) ($row->content ?? ''), 0, 120),
                    'amount' => null,
                    'status' => 'Completed',
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'testimonial_id' => (string) $row->id,
                        'content' => (string) ($row->content ?? ''),
                        'rating' => $row->rating ? (int) $row->rating : null,
                        'member_role' => $isAuthor ? 'author' : 'recipient',
                        'from_user_id' => (string) ($row->from_user_id ?? ''),
                        'to_user_id' => (string) ($row->to_user_id ?? ''),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchRequirements(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('requirements')) {
            return [];
        }

        $q = DB::table('requirements')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $createdDate = $row->created_at ? Carbon::parse($row->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'requirement',
                    'title' => 'Requirement Posted',
                    'counterpart_name' => null,
                    'description' => (string) ($row->subject ?? 'Business requirement'),
                    'amount' => null,
                    'status' => ucfirst((string) ($row->status ?? 'active')),
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'requirement_id' => (string) $row->id,
                        'subject' => (string) ($row->subject ?? ''),
                        'description' => (string) ($row->description ?? ''),
                        'status' => ucfirst((string) ($row->status ?? 'active')),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchPosts(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('posts')) {
            return [];
        }

        $q = DB::table('posts')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where('content_text', 'like', "%{$search}%");
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $createdDate = $row->created_at ? Carbon::parse($row->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'post',
                    'title' => 'Post Created',
                    'counterpart_name' => null,
                    'description' => (string) mb_substr((string) ($row->content_text ?? $row->title ?? ''), 0, 120),
                    'amount' => null,
                    'status' => 'Completed',
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'post_id' => (string) $row->id,
                        'post_type' => (string) ($row->post_type ?? 'post'),
                        'content' => (string) mb_substr((string) ($row->content_text ?? ''), 0, 300),
                        'visibility' => (string) ($row->visibility ?? 'public'),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchCreatives(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('activity_creatives')) {
            return [];
        }

        $q = DB::table('activity_creatives')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $createdDate = $row->created_at ? Carbon::parse($row->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'creative',
                    'title' => 'Creative Shared',
                    'counterpart_name' => null,
                    'description' => (string) ($row->title ?? 'Activity creative'),
                    'amount' => null,
                    'status' => ucfirst((string) ($row->status ?? 'completed')),
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'creative_id' => (string) $row->id,
                        'title' => (string) ($row->title ?? ''),
                        'description' => (string) ($row->description ?? ''),
                        'activity_type' => (string) ($row->activity_type ?? ''),
                        'creative_url' => $row->creative_url ? url('/api/v1/files/'.ltrim((string) $row->creative_url, '/')) : null,
                        'status' => (string) ($row->status ?? 'completed'),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchBadges(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('user_milestone_badges') || ! Schema::hasTable('milestone_badges')) {
            return [];
        }

        $q = DB::table('user_milestone_badges as umb')
            ->join('milestone_badges as mb', 'mb.id', '=', 'umb.badge_id')
            ->where('umb.user_id', $memberId)
            ->where('umb.status', UserMilestoneBadge::STATUS_EARNED)
            ->select(
                'umb.id',
                'umb.earned_at',
                'umb.created_at',
                'umb.achieved_count',
                'umb.milestone_type',
                'mb.id as badge_id',
                'mb.title as badge_title',
                'mb.description as badge_description',
                'mb.badge_image_url',
                'mb.type as badge_type',
                'mb.required_count',
            );

        if ($fromDate) {
            $q->whereDate('umb.earned_at', '>=', $fromDate);
        }

        if ($toDate) {
            $q->whereDate('umb.earned_at', '<=', $toDate);
        }

        if ($search) {
            $q->where('mb.title', 'like', "%{$search}%");
        }

        return $q->orderByDesc('umb.earned_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $earnedTs = $row->earned_at ? strtotime((string) $row->earned_at) : ($row->created_at ? strtotime((string) $row->created_at) : 0);
                $earnedAt = $row->earned_at
                    ? Carbon::parse($row->earned_at)->toIso8601String()
                    : ($row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String());

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'badge',
                    'title' => 'Badge Earned',
                    'counterpart_name' => null,
                    'description' => (string) ($row->badge_title ?? 'Milestone badge'),
                    'amount' => null,
                    'status' => 'Earned',
                    'date' => $earnedAt,
                    'created_at' => $earnedAt,
                    'data' => [
                        'badge_id' => (string) ($row->badge_id ?? ''),
                        'badge_name' => (string) ($row->badge_title ?? ''),
                        'badge_description' => (string) ($row->badge_description ?? ''),
                        'badge_image' => $row->badge_image_url ?? null,
                        'badge_type' => (string) ($row->badge_type ?? ''),
                        'required_count' => (int) ($row->required_count ?? 0),
                        'achieved_count' => (int) ($row->achieved_count ?? 0),
                        'milestone_type' => (string) ($row->milestone_type ?? ''),
                        'earned_at' => $earnedAt,
                    ],
                    '_sort_ts' => $earnedTs,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchEventRegistrations(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('event_registrations')) {
            return [];
        }

        $q = DB::table('event_registrations as er')
            ->leftJoin('events as e', 'e.id', '=', 'er.event_id')
            ->where('er.user_id', $memberId)
            ->whereNull('er.deleted_at')
            ->select(
                'er.id',
                'er.event_id',
                'er.status',
                'er.checkin_status',
                'er.registered_at',
                'er.created_at',
                'e.title as event_title',
                'e.start_at as event_start_at',
            );

        $this->applyDateFilters($q, 'er.created_at', $fromDate, $toDate);

        if ($search) {
            $q->where('e.title', 'like', "%{$search}%");
        }

        return $q->orderByDesc('er.created_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;
                $isoDate = $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String();

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'event_registration',
                    'title' => 'Event Registration',
                    'counterpart_name' => null,
                    'description' => 'Registered for '.(string) ($row->event_title ?? 'an event'),
                    'amount' => null,
                    'status' => ucfirst((string) ($row->status ?? 'registered')),
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'registration_id' => (string) $row->id,
                        'event_id' => (string) ($row->event_id ?? ''),
                        'event_name' => (string) ($row->event_title ?? ''),
                        'event_date' => $row->event_start_at ? Carbon::parse($row->event_start_at)->toDateString() : null,
                        'status' => (string) ($row->status ?? 'registered'),
                        'checkin_status' => (string) ($row->checkin_status ?? 'not_checked_in'),
                        'registered_at' => $row->registered_at ? Carbon::parse($row->registered_at)->toIso8601String() : null,
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEMBER POSTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated posts for a member.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberPosts(string $memberId, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $postType = isset($filters['post_type']) ? trim((string) $filters['post_type']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;

        $query = Post::query()
            ->where('user_id', $memberId)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->withCount(['comments', 'likes']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('content_text', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($postType) {
            $query->where('post_type', $postType);
        }

        if ($fromDate) {
            try {
                $query->whereDate('created_at', '>=', Carbon::parse($fromDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        if ($toDate) {
            try {
                $query->whereDate('created_at', '<=', Carbon::parse($toDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (Post $post): array {
            return [
                'id' => (string) $post->id,
                'content' => (string) ($post->content_text ?? ''),
                'title' => (string) ($post->title ?? ''),
                'post_type' => (string) ($post->post_type ?? 'post'),
                'visibility' => (string) ($post->visibility ?? 'public'),
                'media' => (array) ($post->media ?? []),
                'image' => $post->image,
                'likes_count' => (int) ($post->likes_count ?? 0),
                'comments_count' => (int) ($post->comments_count ?? 0),
                'status' => (string) ($post->moderation_status ?? 'approved'),
                'created_at' => $post->created_at ? $post->created_at->toIso8601String() : null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEMBER CREATIVES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated creatives for a member.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberCreatives(string $memberId, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $activityType = isset($filters['activity_type']) ? trim((string) $filters['activity_type']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;

        $query = ActivityCreative::query()
            ->where('user_id', $memberId)
            ->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($activityType) {
            $query->where('activity_type', $activityType);
        }

        if ($fromDate) {
            try {
                $query->whereDate('created_at', '>=', Carbon::parse($fromDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        if ($toDate) {
            try {
                $query->whereDate('created_at', '<=', Carbon::parse($toDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (ActivityCreative $creative): array {
            $url = null;
            if ($creative->creative_url) {
                $url = str_starts_with((string) $creative->creative_url, 'http')
                    ? (string) $creative->creative_url
                    : url('/api/v1/files/'.ltrim((string) $creative->creative_url, '/'));
            } elseif ($creative->creative_file_id) {
                $url = url('/api/v1/files/'.$creative->creative_file_id);
            }

            return [
                'id' => (string) $creative->id,
                'title' => (string) ($creative->title ?? ''),
                'description' => (string) ($creative->description ?? ''),
                'activity_type' => (string) ($creative->activity_type ?? ''),
                'activity_id' => $creative->activity_id ? (string) $creative->activity_id : null,
                'creative_url' => $url,
                'status' => (string) ($creative->status ?? 'completed'),
                'meta' => (array) ($creative->meta ?? []),
                'created_at' => $creative->created_at ? $creative->created_at->toIso8601String() : null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEMBER BADGES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated earned badges for a member.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberBadges(string $memberId, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $badgeType = isset($filters['badge_type']) ? trim((string) $filters['badge_type']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;

        $query = UserMilestoneBadge::query()
            ->with('badge')
            ->where('user_id', $memberId)
            ->where('status', UserMilestoneBadge::STATUS_EARNED);

        if ($search) {
            $query->whereHas('badge', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        if ($badgeType) {
            $query->whereHas('badge', fn ($q) => $q->where('type', $badgeType));
        }

        if ($fromDate) {
            try {
                $query->whereDate('earned_at', '>=', Carbon::parse($fromDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        if ($toDate) {
            try {
                $query->whereDate('earned_at', '<=', Carbon::parse($toDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        $paginator = $query->orderByDesc('earned_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (UserMilestoneBadge $umb): array {
            $badge = $umb->badge;

            return [
                'id' => (string) $umb->id,
                'badge_id' => $badge ? (string) $badge->id : null,
                'badge_name' => $badge ? (string) $badge->title : null,
                'badge_description' => $badge ? (string) ($badge->description ?? '') : null,
                'badge_image' => $badge ? $badge->badge_image_url : null,
                'badge_type' => $badge ? (string) $badge->type : null,
                'required_count' => $badge ? (int) $badge->required_count : null,
                'achieved_count' => (int) ($umb->achieved_count ?? 0),
                'milestone_type' => (string) ($umb->milestone_type ?? ''),
                'status' => (string) $umb->status,
                'earned_at' => $umb->earned_at ? $umb->earned_at->toIso8601String() : null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEMBER EVENTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated events associated with a member (via registrations).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberEvents(string $memberId, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $eventType = isset($filters['event_type']) ? trim((string) $filters['event_type']) : null;
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;

        // Events the member registered for
        $eventIds = DB::table('event_registrations')
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->pluck('event_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();

        // Also include events created or organized by the member
        $createdOrOrganizedIds = DB::table('events')
            ->where(function ($q) use ($memberId): void {
                $q->where('created_by_user_id', $memberId)
                    ->orWhere('organizer_user_id', $memberId);
            })
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $allEventIds = array_values(array_unique(array_merge($eventIds, $createdOrOrganizedIds)));

        if (empty($allEventIds)) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $query = Event::query()
            ->whereIn('id', $allEventIds)
            ->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location_text', 'like', "%{$search}%");
            });
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($fromDate) {
            try {
                $query->whereDate('start_at', '>=', Carbon::parse($fromDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        if ($toDate) {
            try {
                $query->whereDate('start_at', '<=', Carbon::parse($toDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        $paginator = $query->orderByDesc('start_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $registrationMap = DB::table('event_registrations')
            ->where('user_id', $memberId)
            ->whereIn('event_id', $allEventIds)
            ->whereNull('deleted_at')
            ->select('event_id', 'status', 'checkin_status')
            ->get()
            ->keyBy('event_id');

        $items = collect($paginator->items())->map(function (Event $event) use ($memberId, $registrationMap): array {
            $reg = $registrationMap->get((string) $event->id);

            $isCreator = (string) ($event->created_by_user_id ?? '') === $memberId;
            $isOrganizer = (string) ($event->organizer_user_id ?? '') === $memberId;
            $isRegistered = $reg !== null;

            $roles = [];
            if ($isCreator) {
                $roles[] = 'creator';
            }
            if ($isOrganizer) {
                $roles[] = 'organizer';
            }
            if ($isRegistered) {
                $roles[] = 'participant';
            }

            return [
                'id' => (string) $event->id,
                'title' => (string) ($event->title ?? ''),
                'event_type' => (string) ($event->event_type ?? ''),
                'mode' => (string) ($event->mode ?? ''),
                'location_text' => (string) ($event->location_text ?? ''),
                'start_at' => $event->start_at ? $event->start_at->toIso8601String() : null,
                'end_at' => $event->end_at ? $event->end_at->toIso8601String() : null,
                'status' => $event->computed_status,
                'member_roles' => $roles,
                'registration_status' => $reg ? (string) $reg->status : null,
                'checkin_status' => $reg ? (string) $reg->checkin_status : null,
                'banner_url' => $event->banner_url ?? null,
                'is_paid' => (bool) ($event->is_paid ?? false),
                'ticket_price' => $event->ticket_price ?? null,
                'created_at' => $event->created_at ? $event->created_at->toIso8601String() : null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEMBER EVENT REGISTRATIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated event registrations for a member.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberEventRegistrations(string $memberId, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $eventType = isset($filters['event_type']) ? trim((string) $filters['event_type']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;

        $query = EventRegistration::query()
            ->with(['event', 'occurrence'])
            ->where('user_id', $memberId)
            ->whereNull('deleted_at');

        if ($search) {
            $query->whereHas('event', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($eventType) {
            $query->whereHas('event', fn ($q) => $q->where('event_type', $eventType));
        }

        if ($fromDate) {
            try {
                $query->whereDate('created_at', '>=', Carbon::parse($fromDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        if ($toDate) {
            try {
                $query->whereDate('created_at', '<=', Carbon::parse($toDate)->toDateString());
            } catch (\Throwable) {
            }
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (EventRegistration $reg): array {
            $event = $reg->event;
            $occurrence = $reg->occurrence;

            return [
                'id' => (string) $reg->id,
                'event_id' => $reg->event_id ? (string) $reg->event_id : null,
                'event_name' => $event ? (string) $event->title : null,
                'event_date' => $event?->start_at ? $event->start_at->toDateString() : null,
                'event_type' => $event ? (string) ($event->event_type ?? '') : null,
                'event_status' => $event ? $event->computed_status : null,
                'occurrence_date' => $occurrence?->start_at ? Carbon::parse($occurrence->start_at)->toDateString() : null,
                'registration_status' => (string) ($reg->status ?? 'registered'),
                'checkin_status' => (string) ($reg->checkin_status ?? 'not_checked_in'),
                'registered_at' => $reg->registered_at ? $reg->registered_at->toIso8601String() : ($reg->created_at ? $reg->created_at->toIso8601String() : null),
                'checked_in_at' => $reg->checked_in_at ? $reg->checked_in_at->toIso8601String() : null,
                'payment_required' => (bool) ($reg->payment_required ?? false),
                'payment_status' => $reg->payment_status ?? null,
                'payment_amount' => $reg->payment_amount !== null ? (float) $reg->payment_amount : ($reg->amount !== null ? (float) $reg->amount : null),
                'payment_currency' => $reg->payment_currency ?? $reg->currency ?? null,
                'payment_gateway' => $reg->payment_gateway ?? null,
                'registration_type' => (string) ($reg->registration_type ?? 'member'),
                'source' => (string) ($reg->source ?? 'app'),
                'qr_token' => $reg->qr_token ?? null,
                'qr_code_url' => $reg->qr_code_url ?? null,
                'qr_status' => $reg->qr_status ?? null,
                'created_at' => $reg->created_at ? $reg->created_at->toIso8601String() : null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Apply from_date / to_date filters on a query builder.
     */
    private function applyDateFilters(
        Builder $q,
        string $column,
        ?string $fromDate,
        ?string $toDate,
    ): void {
        if ($fromDate) {
            try {
                $q->whereDate($column, '>=', Carbon::parse($fromDate)->toDateString());
            } catch (\Throwable) {
                // invalid date — skip
            }
        }

        if ($toDate) {
            try {
                $q->whereDate($column, '<=', Carbon::parse($toDate)->toDateString());
            } catch (\Throwable) {
                // invalid date — skip
            }
        }
    }
}
