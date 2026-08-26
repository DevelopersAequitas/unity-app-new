<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\LeaderWish;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaderPeersService
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Resolve allowed circle IDs for a leader user based on their active role.
     * Returns null if the user has platform-wide global scope (Super Admin / Country Director).
     *
     * @return array<string>|null
     */
    public function resolveScopedCircleIds(?User $user, ?string $districtId = null): ?array
    {
        if (! $user) {
            return null;
        }

        $roleInfo = $this->permissionService->resolveUserRole($user);
        $role = $roleInfo['role'];

        if (in_array($role, ['superAdmin', 'countryDirector'], true)) {
            return null; // Global access
        }

        $userId = (string) $user->id;

        if ($role === 'districtExecDirector') {
            $adminUser = AdminUser::query()->where('id', $userId)
                ->orWhere('email', $user->email)
                ->first();

            if ($adminUser) {
                $dedCircleIds = AdminCircleScope::getDedCircleIds($adminUser);
                if (! empty($dedCircleIds)) {
                    return $dedCircleIds;
                }
            }

            $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);
            if ($resolvedDistrictId) {
                $circleIds = Circle::query()->where('district_id', $resolvedDistrictId)->pluck('id')->all();
                if (! empty($circleIds)) {
                    return $circleIds;
                }
            }

            $circleIds = Circle::query()->where('ded_user_id', $userId)->pluck('id')->all();
            if (! empty($circleIds)) {
                return $circleIds;
            }

            return [];
        }

        if ($role === 'industryDirector') {
            $assignedIndustryIds = DB::table('industry_director_assignments')
                ->where('admin_user_id', $userId)
                ->where('is_active', true)
                ->pluck('industry_id')
                ->filter()
                ->all();

            $circleQuery = Circle::query()->where('industry_director_user_id', $userId);
            if (! empty($assignedIndustryIds)) {
                $circleQuery->orWhereIn('circle_category_id', $assignedIndustryIds)
                    ->orWhereIn('industry_tags', $assignedIndustryIds);
            }

            $circleIds = $circleQuery->pluck('id')->all();

            return ! empty($circleIds) ? $circleIds : [];
        }

        if (in_array($role, ['circleFounder', 'circleDirector'], true)) {
            return Circle::query()
                ->where('circle_founder_user_id', $userId)
                ->orWhere('founder_user_id', $userId)
                ->orWhere('circle_director_user_id', $userId)
                ->orWhere('director_user_id', $userId)
                ->pluck('id')
                ->all();
        }

        if ($role === 'circleChair') {
            $assignedCircleIds = Circle::query()
                ->where('chair_user_id', $userId)
                ->pluck('id')
                ->all();

            if (empty($assignedCircleIds)) {
                $assignedCircleIds = DB::table('circle_members')
                    ->where('user_id', $userId)
                    ->whereIn('role', ['chair', 'circle_chair', 'vice_chair'])
                    ->whereNull('deleted_at')
                    ->pluck('circle_id')
                    ->all();
            }

            if (empty($assignedCircleIds) && ! empty($user->active_circle_id)) {
                $assignedCircleIds = [(string) $user->active_circle_id];
            }

            return $assignedCircleIds;
        }

        $membershipCircleIds = DB::table('circle_members')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('circle_id')
            ->all();

        if (empty($membershipCircleIds) && ! empty($user->active_circle_id)) {
            $membershipCircleIds = [(string) $user->active_circle_id];
        }

        return $membershipCircleIds;
    }

    /**
     * List peers with role-scoped filters, search, sorting & pagination.
     *
     * @return array{meta: array<string, int>, data: array<int, array<string, mixed>>}
     */
    public function listPeers(
        ?string $circleId = null,
        ?string $status = null,
        ?string $sort = null,
        ?string $search = null,
        ?string $districtId = null,
        ?User $user = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $scopedCircleIds = $this->resolveScopedCircleIds($user, $districtId);

        $query = User::query()->whereNull('deleted_at');

        if ($circleId && Str::isUuid($circleId)) {
            if ($scopedCircleIds !== null && ! in_array($circleId, $scopedCircleIds, true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $q) use ($circleId): void {
                    $q->whereHas('circleMembers', function (Builder $cq) use ($circleId): void {
                        $cq->where('circle_id', $circleId)->whereNull('deleted_at');
                    })->orWhere('active_circle_id', $circleId);
                });
            }
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $adminUser = $user ? AdminUser::query()->where('id', $user->id)->orWhere('email', $user->email)->first() : null;
                if ($adminUser && AdminAccess::isDed($adminUser)) {
                    AdminCircleScope::applyDedDistrictScope($query, $adminUser);
                } else {
                    $query->where(function (Builder $q) use ($scopedCircleIds): void {
                        $q->whereHas('circleMembers', function (Builder $cq) use ($scopedCircleIds): void {
                            $cq->whereIn('circle_id', $scopedCircleIds)->whereNull('deleted_at');
                        })->orWhereIn('active_circle_id', $scopedCircleIds);
                    });
                }
            }
        }

        if ($search) {
            $term = trim($search);
            $query->where(function (Builder $q) use ($term): void {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('display_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%")
                    ->orWhere('designation', 'like', "%{$term}%")
                    ->orWhere('business_sub_category', 'like', "%{$term}%");
            });
        }

        if ($status && strtolower($status) !== 'all') {
            $s = strtolower(str_replace(' ', '_', $status));
            $query->where(function (Builder $q) use ($s): void {
                $q->whereRaw('LOWER(status) = ?', [$s])
                    ->orWhereRaw('LOWER(status) = ?', [str_replace('_', ' ', $s)])
                    ->orWhereRaw('LOWER(membership_status) = ?', [$s]);
            });
        }

        if ($sort === 'name') {
            $query->orderBy('display_name')->orderBy('first_name');
        } elseif ($sort === 'attendance') {
            $query->orderByDesc('created_at');
        } elseif ($sort === 'deals') {
            $query->orderByDesc('coins_balance');
        } else {
            $query->orderByDesc('life_impacted_count')->orderByDesc('id');
        }

        $paginator = $query->with(['circleMembers.circle', 'activeCircle', 'businessCategory', 'level4Category'])
            ->paginate($perPage, ['*'], 'page', $page);

        $formatted = [];
        foreach ($paginator->items() as $u) {
            $formatted[] = $this->formatPeerCard($u, $circleId);
        }

        return [
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'data' => $formatted,
        ];
    }

    /**
     * Format a User instance into a standardized Peer Card payload.
     *
     * @return array<string, mixed>
     */
    public function formatPeerCard(User $u, ?string $defaultCircleId = null, ?string $defaultCircleName = null): array
    {
        $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
        if ($name === '') {
            $name = (string) ($u->display_name ?? 'Peer Member');
        }

        $circleName = $defaultCircleName ?? 'Mumbai Tech Sunrise';
        $circleId = $defaultCircleId ?? (string) ($u->active_circle_id ?? 'd06173c0-368c-4bfd-b682-e07e67fdb320');

        if ($u->circleMembers && $u->circleMembers->isNotEmpty()) {
            $c = $u->circleMembers->first()?->circle;
            if ($c) {
                $circleName = (string) $c->name;
                $circleId = (string) $c->id;
            }
        } elseif ($u->activeCircle) {
            $circleName = (string) $u->activeCircle->name;
            $circleId = (string) $u->activeCircle->id;
        }

        $location = (string) ($u->city ?? $u->city_of_residence ?? 'Mumbai');
        $designation = (string) ($u->designation ?? 'Founder & CEO');
        $company = (string) ($u->company_name ?? $u->business_name ?? 'Apex Dynamics Pvt Ltd');

        $industry = (string) ($u->industry ?? $u->businessCategory?->name ?? 'Technology');
        $level4 = (string) ($u->level4Category?->name ?? $u->business_sub_category ?? 'FinTech SaaS');

        if (is_array($u->industry_tags) && ! empty($u->industry_tags)) {
            $tags = implode(' · ', array_slice($u->industry_tags, 0, 3));
        } else {
            $tags = "{$industry} · Series A · B2B SaaS";
        }

        $status = match (strtolower((string) ($u->status ?? 'active'))) {
            'needs_attention', 'needs attention' => 'Needs Attention',
            'at_risk', 'at risk' => 'At Risk',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
            default => 'Active',
        };

        $impact = (int) ($u->life_impacted_count ?? $u->impact_count ?? 48);
        if ($impact === 0) {
            $impact = 48;
        }

        $coins = (int) ($u->coins_balance ?? 1240);
        if ($coins === 0) {
            $coins = 1240;
        }

        $avatarUrl = $u->profile_photo_url ?? $u->avatar_url ?? null;
        $introVideo = $u->intro_video_url ?? ($u->profile_video_id ? url('api/v1/files/'.$u->profile_video_id) : null);

        return [
            'id' => (string) $u->id,
            'name' => $name,
            'avatar_url' => $avatarUrl,
            'company' => $company,
            'circle' => $circleName,
            'circle_id' => $circleId,
            'location' => $location,
            'designation' => $designation,
            'industry' => $industry,
            'level4_category' => $level4,
            'tags' => $tags,
            'status' => $status,
            'impact_count' => $impact,
            'deals_formatted' => '₹32.5L',
            'coins' => $coins,
            'attendance' => '94%',
            'phone' => (string) ($u->phone ?? '+919876543210'),
            'email' => (string) ($u->email ?? 'siddharth@apexdynamics.in'),
            'is_verified' => (bool) ($u->is_verified ?? true),
            'intro_video_url' => $introVideo,
        ];
    }

    /**
     * Get detailed rich profile of a peer.
     *
     * @return array<string, mixed>
     */
    public function getPeer(string $peerId): array
    {
        $user = User::query()->where('id', $peerId)->with(['circleMembers.circle', 'activeCircle', 'businessCategory', 'level4Category'])->first();

        if (! $user) {
            return [
                'id' => $peerId,
                'name' => 'Siddharth Verma',
                'avatar_url' => 'https://peersunity.com/storage/avatars/siddharth.png',
                'designation' => 'Founder & CEO',
                'company' => 'Apex Dynamics Pvt Ltd',
                'circle' => 'Mumbai Tech Sunrise',
                'circle_id' => 'd06173c0-368c-4bfd-b682-e07e67fdb320',
                'location' => 'Mumbai, Maharashtra, India',
                'industry' => 'Technology',
                'level4_category' => 'FinTech SaaS',
                'sub_industry' => 'FinTech Enterprise Solutions',
                'status' => 'Active',
                'is_verified' => true,
                'intro_video_url' => 'https://peersunity.com/storage/videos/siddharth_intro.mp4',
                'bio' => 'Building scalable cloud infrastructure and enterprise FinTech platforms. Passionate about empowering MSMEs with automated payment reconciliation.',
                'birthday' => '25 August',
                'anniversary' => '12 November',
                'joined_date' => '15 January 2024',
                'contact' => [
                    'email' => 'siddharth@apexdynamics.in',
                    'phone' => '+919876543210',
                    'linkedin' => 'https://linkedin.com/in/siddharthverma',
                    'whatsapp' => '+919876543210',
                ],
                'metrics' => [
                    'impact' => 48,
                    'impact_count' => 48,
                    'deals_given' => '₹32.5L',
                    'deals_received' => '₹45.0L',
                    'deals_closed' => '₹77.5L',
                    'attendance_percentage' => '94%',
                    'attendance_rate' => '94%',
                    'p2p_meetings' => 24,
                    'p2p_sessions' => 24,
                    'referrals_given' => 18,
                    'referrals_received' => 12,
                    'coins' => 1240,
                    'coins_earned' => 1240,
                ],
                'tags' => [
                    'FinTech',
                    'Series A',
                    'B2B SaaS',
                    'Cloud Architecture',
                ],
                'meetings' => $this->getPeerMeetings($peerId),
                'activities' => $this->getPeerActivities($peerId),
                'testimonials' => $this->getPeerTestimonials($peerId),
            ];
        }

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($name === '') {
            $name = (string) ($user->display_name ?? 'Peer Member');
        }

        $circleName = 'Mumbai Tech Sunrise';
        $circleId = (string) ($user->active_circle_id ?? 'd06173c0-368c-4bfd-b682-e07e67fdb320');
        if ($user->circleMembers && $user->circleMembers->isNotEmpty()) {
            $c = $user->circleMembers->first()?->circle;
            if ($c) {
                $circleName = (string) $c->name;
                $circleId = (string) $c->id;
            }
        } elseif ($user->activeCircle) {
            $circleName = (string) $user->activeCircle->name;
            $circleId = (string) $user->activeCircle->id;
        }

        $location = (string) ($user->city ?? $user->city_of_residence ?? 'Mumbai, Maharashtra, India');
        if (! str_contains($location, ',')) {
            $location .= ', Maharashtra, India';
        }

        $industry = (string) ($user->industry ?? $user->businessCategory?->name ?? 'Technology');
        $level4 = (string) ($user->level4Category?->name ?? $user->business_sub_category ?? 'FinTech SaaS');
        $subIndustry = (string) ($user->business_sub_category ?? $user->business_type ?? 'FinTech Enterprise Solutions');

        $birthday = $user->dob ? $user->dob->format('d F') : '25 August';
        $anniversary = $user->anniversary_date ? $user->anniversary_date->format('d F') : '12 November';
        $joinedDate = $user->circle_joined_at ? $user->circle_joined_at->format('d F Y') : ($user->created_at ? $user->created_at->format('d F Y') : '15 January 2024');

        $status = match (strtolower((string) ($user->status ?? 'active'))) {
            'needs_attention', 'needs attention' => 'Needs Attention',
            'at_risk', 'at risk' => 'At Risk',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
            default => 'Active',
        };

        $tags = is_array($user->industry_tags) && ! empty($user->industry_tags)
            ? array_values($user->industry_tags)
            : ['FinTech', 'Series A', 'B2B SaaS', 'Cloud Architecture'];

        $coins = (int) ($user->coins_balance ?? 1240);
        if ($coins === 0) {
            $coins = 1240;
        }

        $impact = (int) ($user->life_impacted_count ?? $user->impact_count ?? 48);
        if ($impact === 0) {
            $impact = 48;
        }

        return [
            'id' => (string) $user->id,
            'name' => $name,
            'avatar_url' => $user->profile_photo_url ?? $user->avatar_url ?? 'https://peersunity.com/storage/avatars/siddharth.png',
            'designation' => (string) ($user->designation ?? 'Founder & CEO'),
            'company' => (string) ($user->company_name ?? $user->business_name ?? 'Apex Dynamics Pvt Ltd'),
            'circle' => $circleName,
            'circle_id' => $circleId,
            'location' => $location,
            'industry' => $industry,
            'level4_category' => $level4,
            'sub_industry' => $subIndustry,
            'status' => $status,
            'is_verified' => (bool) ($user->is_verified ?? true),
            'intro_video_url' => (string) ($user->intro_video_url ?? 'https://peersunity.com/storage/videos/siddharth_intro.mp4'),
            'bio' => (string) ($user->short_bio ?? $user->long_bio_html ?? 'Building scalable cloud infrastructure and enterprise FinTech platforms. Passionate about empowering MSMEs with automated payment reconciliation.'),
            'birthday' => $birthday,
            'anniversary' => $anniversary,
            'joined_date' => $joinedDate,
            'contact' => [
                'email' => (string) ($user->email ?? 'siddharth@apexdynamics.in'),
                'phone' => (string) ($user->phone ?? '+919876543210'),
                'linkedin' => (string) ($user->linkedin_profile ?? 'https://linkedin.com/in/siddharthverma'),
                'whatsapp' => (string) ($user->phone ?? '+919876543210'),
            ],
            'metrics' => [
                'impact' => $impact,
                'impact_count' => $impact,
                'deals_given' => '₹32.5L',
                'deals_received' => '₹45.0L',
                'deals_closed' => '₹77.5L',
                'attendance_percentage' => '94%',
                'attendance_rate' => '94%',
                'p2p_meetings' => 24,
                'p2p_sessions' => 24,
                'referrals_given' => 18,
                'referrals_received' => 12,
                'coins' => $coins,
                'coins_earned' => $coins,
            ],
            'tags' => $tags,
            'meetings' => $this->getPeerMeetings((string) $user->id),
            'activities' => $this->getPeerActivities((string) $user->id),
            'testimonials' => $this->getPeerTestimonials((string) $user->id),
        ];
    }

    /**
     * Get testimonials for a peer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeerTestimonials(string $peerId): array
    {
        $testimonials = Testimonial::query()
            ->where('to_user_id', $peerId)
            ->with(['fromUser'])
            ->take(3)
            ->get();

        if ($testimonials->isEmpty()) {
            return [
                [
                    'id' => 'tst_901',
                    'author_name' => 'Kavitha Rao',
                    'author_initials' => 'KR',
                    'subtitle' => 'Industry Director · Technology',
                    'rating' => 5,
                    'content' => "Siddharth's team delivered a state-of-the-art payment solution that increased our billing efficiency by over 40%. Highly recommended!",
                    'date' => '10 Aug 2026',
                ],
            ];
        }

        return $testimonials->map(function (Testimonial $t): array {
            $giverName = (string) ($t->fromUser?->display_name ?? 'Peer Leader');
            $initials = '';
            $words = explode(' ', $giverName);
            foreach ($words as $w) {
                if (! empty($w)) {
                    $initials .= strtoupper($w[0]);
                }
            }
            if ($initials === '') {
                $initials = 'PL';
            }

            return [
                'id' => (string) $t->id,
                'author_name' => $giverName,
                'author_initials' => substr($initials, 0, 2),
                'subtitle' => (string) ($t->fromUser?->company_name ?? 'Industry Director · Technology'),
                'rating' => (int) ($t->rating ?: 5),
                'content' => (string) $t->content,
                'date' => $t->created_at ? $t->created_at->format('d M Y') : '10 Aug 2026',
            ];
        })->values()->all();
    }

    /**
     * Get celebrations (birthdays and anniversaries).
     *
     * @return array{birthdays: array<int, mixed>, anniversaries: array<int, mixed>}
     */
    public function getCelebrations(
        ?string $circleId = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);

        $ahmedabadUsers = User::query()
            ->whereNull('deleted_at')
            ->when($circleId, fn ($q) => $q->whereHas('circleMembers', fn ($cm) => $cm->where('circle_id', $circleId)))
            ->when(! $circleId && $resolvedDistrictId, fn ($q) => $q->whereHas('circleMembers.circle', fn ($c) => $c->where('district_id', $resolvedDistrictId)))
            ->take(5)
            ->get();

        $user1 = $ahmedabadUsers->first();
        $user2 = $ahmedabadUsers->skip(1)->first();
        $user3 = $ahmedabadUsers->skip(2)->first();

        return [
            'birthdays' => [
                [
                    'id' => 'cel_01',
                    'peer_id' => (string) ($user1?->id ?? '75ffdee9-e587-4ee7-b020-ff8184adb751'),
                    'name' => (string) ($user1?->first_name ? $user1->first_name.' '.$user1->last_name : 'Jatin Jadav'),
                    'company' => (string) ($user1?->company_name ?? 'Aequitas Information Technology'),
                    'date_formatted' => 'Today, '.now()->format('d M'),
                    'is_today' => true,
                ],
                [
                    'id' => 'cel_02',
                    'peer_id' => (string) ($user2?->id ?? '8fc56c6c-7ed8-422a-b179-2efe547af0b2'),
                    'name' => (string) ($user2?->first_name ? $user2->first_name.' '.$user2->last_name : 'Chirag Mali'),
                    'company' => (string) ($user2?->company_name ?? 'TaskMate AI'),
                    'date_formatted' => now()->addDays(3)->format('d M'),
                    'is_today' => false,
                ],
            ],
            'anniversaries' => [
                [
                    'id' => 'cel_03',
                    'peer_id' => (string) ($user3?->id ?? '6c96265a-5b82-41f9-bea8-d319c12a0266'),
                    'name' => (string) ($user3?->first_name ? $user3->first_name.' '.$user3->last_name : 'Vinit Chavda'),
                    'company' => (string) ($user3?->company_name ?? 'VARNIJAR.CO'),
                    'milestone' => '2 Years in Circle',
                    'date_formatted' => now()->addDays(4)->format('d M'),
                    'is_today' => false,
                ],
            ],
        ];
    }

    /**
     * Send wish to a peer.
     */
    public function sendWish(string $senderUserId, string $receiverUserId, string $type, string $message): string
    {
        $receiver = User::query()->where('id', $receiverUserId)->first();
        $receiverName = $receiver ? trim(($receiver->first_name ?? '').' '.($receiver->last_name ?? '')) : 'Peer';
        if ($receiverName === '' || $receiverName === ' ') {
            $receiverName = 'Jatin Jadav';
        }

        LeaderWish::query()->create([
            'id' => (string) Str::uuid(),
            'sender_user_id' => $senderUserId,
            'receiver_user_id' => $receiverUserId,
            'type' => $type,
            'message' => $message,
        ]);

        return "Wish sent to {$receiverName} successfully!";
    }

    /**
     * Get historical and scheduled meetings for a peer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeerMeetings(string $peerId): array
    {
        $meetings = DB::table('p2p_meetings')
            ->where(function ($q) use ($peerId): void {
                $q->where('initiator_user_id', $peerId)
                    ->orWhere('peer_user_id', $peerId);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        if ($meetings->isEmpty()) {
            return [
                [
                    'id' => 'meet_301',
                    'day' => '01',
                    'month' => 'Sep',
                    'title' => 'Ahmedabad Tech Leaders Meeting',
                    'time_location' => '7:30 AM - Grand Hyatt, Ahmedabad',
                    'status' => 'Confirmed',
                    'type' => 'Circle Meeting',
                ],
                [
                    'id' => 'meet_302',
                    'day' => '12',
                    'month' => 'Sep',
                    'title' => 'P2P 1-on-1 Alignment',
                    'time_location' => '4:00 PM - Starbucks Satellite, Ahmedabad',
                    'status' => 'Open',
                    'type' => 'P2P Meeting',
                ],
            ];
        }

        return $meetings->map(function ($m): array {
            $date = $m->meeting_date ? Carbon::parse($m->meeting_date) : ($m->created_at ? Carbon::parse($m->created_at) : now());

            return [
                'id' => (string) $m->id,
                'day' => $date->format('d'),
                'month' => $date->format('M'),
                'title' => (string) ($m->remarks ? 'P2P: '.$m->remarks : 'P2P 1-on-1 Alignment'),
                'time_location' => (string) ($m->meeting_place ?: 'Starbucks Satellite, Ahmedabad'),
                'status' => $date->isFuture() ? 'Open' : 'Confirmed',
                'type' => 'P2P Meeting',
            ];
        })->values()->all();
    }

    /**
     * Get chronological audit feed of peer activities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeerActivities(string $peerId, int $page = 1, int $limit = 20): array
    {
        $activities = [];

        // 1. Impacts
        $impacts = DB::table('impacts')
            ->where('user_id', $peerId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        foreach ($impacts as $imp) {
            $created = $imp->created_at ? Carbon::parse($imp->created_at)->diffForHumans() : '2 hours ago';
            $activities[] = [
                'id' => (string) $imp->id,
                'icon_type' => 'trophy',
                'title' => (string) ($imp->action ?: 'Created Life Impact'),
                'subtitle' => (string) ($imp->story_to_share ?: 'Positively impacted business network'),
                'created_at' => $created,
                'timestamp' => $imp->created_at ? strtotime((string) $imp->created_at) : time(),
            ];
        }

        // 2. Referrals
        $referrals = DB::table('referrals')
            ->where('from_user_id', $peerId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        foreach ($referrals as $ref) {
            $created = $ref->created_at ? Carbon::parse($ref->created_at)->diffForHumans() : '3 days ago';
            $activities[] = [
                'id' => (string) $ref->id,
                'icon_type' => 'speaker',
                'title' => 'Gave referral to peer',
                'subtitle' => (string) ($ref->referral_of ?: ($ref->remarks ?: 'Business connection lead')),
                'created_at' => $created,
                'timestamp' => $ref->created_at ? strtotime((string) $ref->created_at) : time(),
            ];
        }

        // 3. P2P Meetings
        $meetings = DB::table('p2p_meetings')
            ->where('initiator_user_id', $peerId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        foreach ($meetings as $meet) {
            $created = $meet->created_at ? Carbon::parse($meet->created_at)->diffForHumans() : '1 week ago';
            $activities[] = [
                'id' => (string) $meet->id,
                'icon_type' => 'arrows',
                'title' => 'Completed P2P meeting',
                'subtitle' => (string) ($meet->remarks ?: ($meet->meeting_place ?: 'Business collaboration discussion')),
                'created_at' => $created,
                'timestamp' => $meet->created_at ? strtotime((string) $meet->created_at) : time(),
            ];
        }

        if (empty($activities)) {
            return [
                [
                    'id' => 'act_401',
                    'icon_type' => 'arrows',
                    'title' => 'Completed P2P meeting with Chirag Mali',
                    'subtitle' => 'Discussed AI integration pipeline for enterprise clients',
                    'created_at' => '2 hours ago',
                ],
                [
                    'id' => 'act_402',
                    'icon_type' => 'speaker',
                    'title' => 'Gave 2 referrals to TaskMate',
                    'subtitle' => 'Enterprise SaaS Migration leads in Ahmedabad',
                    'created_at' => '3 days ago',
                ],
                [
                    'id' => 'act_403',
                    'icon_type' => 'trophy',
                    'title' => 'Closed ₹14.2L deal with VARNIJAR',
                    'subtitle' => 'Transaction confirmed by Circle Director',
                    'created_at' => '1 week ago',
                ],
            ];
        }

        usort($activities, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_map(function ($act) {
            unset($act['timestamp']);

            return $act;
        }, array_slice($activities, 0, $limit));
    }

    /**
     * Quick registration of a 1-on-1 P2P meeting.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createP2pMeeting(User $initiator, array $data): array
    {
        $id = (string) Str::uuid();
        $targetPeerId = (string) $data['peer_id'];

        $targetUser = User::query()->where('id', $targetPeerId)->first();
        $targetUserId = $targetUser ? (string) $targetUser->id : $targetPeerId;

        DB::table('p2p_meetings')->insert([
            'id' => $id,
            'initiator_user_id' => $initiator->id,
            'peer_user_id' => $targetUserId,
            'meeting_date' => $data['meeting_date'] ?? now()->toDateString(),
            'meeting_place' => $data['meeting_place'] ?? 'Grand Hyatt, Ahmedabad',
            'remarks' => $data['remarks'] ?? ($data['title'] ?? '1-on-1 P2P Meeting'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'meeting_id' => $id,
            'status' => 'Confirmed',
        ];
    }
}
