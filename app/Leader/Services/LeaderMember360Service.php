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
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $activityType = isset($filters['activity_type']) ? trim((string) $filters['activity_type']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        // Determine which activity types to load
        $allTypes = [
            'life_impact', 'referral', 'p2p_meeting', 'business_deal',
            'testimonial', 'requirement', 'post', 'creative', 'badge', 'event_registration',
        ];

        $types = ($activityType && in_array($activityType, $allTypes, true))
            ? [$activityType]
            : $allTypes;

        // Fetch buffer = perPage * 3 per type to allow proper sorting+pagination
        $buffer = $perPage * 3;
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

        // Sort all by created_at descending
        usort($activities, function (array $a, array $b): int {
            $tsA = $a['_sort_ts'] ?? 0;
            $tsB = $b['_sort_ts'] ?? 0;

            return $tsB <=> $tsA;
        });

        // Remove internal sort key
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
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
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
            'life_impact' => $this->fetchImpacts($memberId, $fromDate, $toDate, $search, $limit),
            'referral' => $this->fetchReferrals($memberId, $fromDate, $toDate, $search, $limit),
            'p2p_meeting' => $this->fetchP2pMeetings($memberId, $fromDate, $toDate, $search, $limit),
            'business_deal' => $this->fetchBusinessDeals($memberId, $fromDate, $toDate, $search, $limit),
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
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'life_impact',
                    'title' => 'Life Impact Created',
                    'description' => (string) ($row->action ?? 'Provided mentorship'),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
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
    private function fetchReferrals(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('referrals')) {
            return [];
        }

        $q = DB::table('referrals')
            ->where('from_user_id', $memberId)
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('referral_of', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row): array {
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'referral',
                    'title' => 'Referral Created',
                    'description' => 'Member gave a referral to '.(string) ($row->referral_of ?? 'a prospect'),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
                    'data' => [
                        'referral_id' => (string) $row->id,
                        'referral_of' => (string) ($row->referral_of ?? ''),
                        'referral_type' => (string) ($row->referral_type ?? 'b2b_referral'),
                        'status' => ucfirst((string) ($row->status ?? 'pending')),
                        'referral_date' => $row->referral_date ? (string) $row->referral_date : null,
                        'remarks' => (string) ($row->remarks ?? ''),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
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

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('remarks', 'like', "%{$search}%")
                    ->orWhere('meeting_place', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($memberId): array {
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;
                $role = ((string) $row->initiator_user_id === $memberId) ? 'initiator' : 'peer';

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'p2p_meeting',
                    'title' => 'P2P Meeting',
                    'description' => (string) ($row->remarks ?? 'One-on-one meeting'),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
                    'data' => [
                        'meeting_id' => (string) $row->id,
                        'meeting_date' => $row->meeting_date ? (string) $row->meeting_date : null,
                        'meeting_place' => (string) ($row->meeting_place ?? ''),
                        'remarks' => (string) ($row->remarks ?? ''),
                        'member_role' => $role,
                        'initiator_user_id' => (string) ($row->initiator_user_id ?? ''),
                        'peer_user_id' => (string) ($row->peer_user_id ?? ''),
                    ],
                    '_sort_ts' => $ts,
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchBusinessDeals(string $memberId, ?string $fromDate, ?string $toDate, ?string $search, int $limit): array
    {
        if (! Schema::hasTable('business_deals')) {
            return [];
        }

        $q = DB::table('business_deals')
            ->where(function ($sq) use ($memberId): void {
                $sq->where('from_user_id', $memberId)
                    ->orWhere('to_user_id', $memberId);
            })
            ->whereNull('deleted_at');

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('comment', 'like', "%{$search}%")
                    ->orWhere('business_type', 'like', "%{$search}%");
            });
        }

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function ($row) use ($memberId): array {
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;
                $amount = (float) ($row->deal_amount ?? 0);
                $role = ((string) ($row->from_user_id ?? '') === $memberId) ? 'giver' : 'receiver';

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'business_deal',
                    'title' => 'Business Deal',
                    'description' => ucwords(str_replace('_', ' ', (string) ($row->business_type ?? 'New Business'))).' deal',
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
                    'data' => [
                        'deal_id' => (string) $row->id,
                        'deal_amount' => $amount,
                        'business_type' => (string) ($row->business_type ?? ''),
                        'deal_date' => $row->deal_date ? (string) $row->deal_date : null,
                        'comment' => (string) ($row->comment ?? ''),
                        'member_role' => $role,
                        'from_user_id' => (string) ($row->from_user_id ?? ''),
                        'to_user_id' => (string) ($row->to_user_id ?? ''),
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
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;
                $role = ((string) ($row->from_user_id ?? '') === $memberId) ? 'author' : 'recipient';

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'testimonial',
                    'title' => $role === 'author' ? 'Testimonial Given' : 'Testimonial Received',
                    'description' => (string) mb_substr((string) ($row->content ?? ''), 0, 120),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
                    'data' => [
                        'testimonial_id' => (string) $row->id,
                        'content' => (string) ($row->content ?? ''),
                        'rating' => $row->rating ? (int) $row->rating : null,
                        'member_role' => $role,
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
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'requirement',
                    'title' => 'Requirement Posted',
                    'description' => (string) ($row->subject ?? 'Business requirement'),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
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
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'post',
                    'title' => 'Post Created',
                    'description' => (string) mb_substr((string) ($row->content_text ?? $row->title ?? ''), 0, 120),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
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
                $ts = $row->created_at ? strtotime((string) $row->created_at) : 0;

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'creative',
                    'title' => 'Creative Shared',
                    'description' => (string) ($row->title ?? 'Activity creative'),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
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
                    'description' => (string) ($row->badge_title ?? 'Milestone badge'),
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

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'event_registration',
                    'title' => 'Event Registration',
                    'description' => 'Registered for '.(string) ($row->event_title ?? 'an event'),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : now()->toIso8601String(),
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

        $paginator = Post::query()
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->withCount(['comments', 'likes'])
            ->orderByDesc('created_at')
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

        $paginator = ActivityCreative::query()
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
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

        $paginator = UserMilestoneBadge::query()
            ->with('badge')
            ->where('user_id', $memberId)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->orderByDesc('earned_at')
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

        $paginator = Event::query()
            ->whereIn('id', $allEventIds)
            ->whereNull('deleted_at')
            ->orderByDesc('start_at')
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

        $paginator = EventRegistration::query()
            ->with(['event', 'occurrence'])
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
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
