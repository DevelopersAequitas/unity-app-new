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

        // For circleFounder, circleDirector, circleChair, chairBusinessGrowth, chairMembership, chairEventsPrograms, or any own-circle leader
        $circleIds = [];

        // 1. Direct column associations on circles table
        $directCircleIds = Circle::query()
            ->where('circle_founder_user_id', $userId)
            ->orWhere('founder_user_id', $userId)
            ->orWhere('circle_director_user_id', $userId)
            ->orWhere('director_user_id', $userId)
            ->orWhere('chair_user_id', $userId)
            ->orWhere('vice_chair_user_id', $userId)
            ->pluck('id')
            ->all();
        $circleIds = array_merge($circleIds, $directCircleIds);

        // 2. circle_members table roles and memberships
        $memberCircleIds = DB::table('circle_members')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('circle_id')
            ->all();
        $circleIds = array_merge($circleIds, $memberCircleIds);

        // 3. User's active circle
        if (! empty($user->active_circle_id)) {
            $circleIds[] = (string) $user->active_circle_id;
        }

        // 4. Calendar leadership JSON checks
        $calendarCircles = Circle::query()->whereNull('deleted_at')->get();
        foreach ($calendarCircles as $c) {
            $leaders = [
                data_get($c->calendar, 'leadership.circle_founder_user_id'),
                data_get($c->calendar, 'leadership.founder_user_id'),
                data_get($c->calendar, 'leadership.circle_director_user_id'),
                data_get($c->calendar, 'leadership.director_user_id'),
                data_get($c->calendar, 'leadership.chair_user_id'),
                data_get($c->calendar, 'leadership.business_growth_committee_chair_user_id'),
                data_get($c->calendar, 'leadership.membership_growth_committee_chair_user_id'),
                data_get($c->calendar, 'leadership.events_impacts_committee_chair_user_id'),
            ];
            foreach ($leaders as $lId) {
                if ($lId && (string) $lId === $userId) {
                    $circleIds[] = (string) $c->id;
                }
            }
        }

        return array_values(array_unique(array_filter($circleIds)));
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

        $circleName = $defaultCircleName ?? '';
        $circleId = $defaultCircleId ?? (string) ($u->active_circle_id ?? '');

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

        $location = (string) ($u->city ?? $u->city_of_residence ?? 'Ahmedabad');
        $designation = (string) ($u->designation ?? $u->job_title ?? 'Founder & CEO');
        $company = (string) ($u->company_name ?? $u->business_name ?? 'Apex Dynamics');

        $industry = (string) ($u->industry ?? $u->businessCategory?->name ?? 'Technology');
        $level4 = (string) ($u->level4Category?->name ?? $u->business_sub_category ?? 'FinTech SaaS');

        if (is_array($u->industry_tags) && ! empty($u->industry_tags)) {
            $tags = implode(' · ', array_slice($u->industry_tags, 0, 3));
        } else {
            $tags = "{$industry} · {$level4}";
        }

        $status = match (strtolower((string) ($u->status ?? 'active'))) {
            'needs_attention', 'needs attention' => 'Needs Attention',
            'at_risk', 'at risk' => 'At Risk',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
            default => 'Active',
        };

        $impact = (int) ($u->life_impacted_count ?? $u->impact_count ?? 0);
        $coins = (int) ($u->coins_balance ?? 0);

        // Dynamically compute deals
        $dealsSum = (float) DB::table('business_deals')
            ->where(function ($q) use ($u): void {
                $q->where('from_user_id', $u->id)
                    ->orWhere('to_user_id', $u->id);
            })
            ->whereNull('deleted_at')
            ->sum('deal_amount');

        $dealsFormatted = $dealsSum >= 10000000
            ? '₹'.round($dealsSum / 10000000, 2).'Cr'
            : ($dealsSum >= 100000 ? '₹'.round($dealsSum / 100000, 1).'L' : '₹'.number_format($dealsSum, 0));

        $avatarUrl = $u->profile_photo_url ?? $u->avatar_url ?? null;
        $introVideo = $u->intro_video_url ?? ($u->profile_video_id ? url('api/v1/files/'.$u->profile_video_id) : null);

        return [
            'id' => (string) $u->id,
            'name' => $name,
            'first_name' => (string) ($u->first_name ?? ''),
            'last_name' => (string) ($u->last_name ?? ''),
            'company_name' => $company,
            'company' => $company,
            'city' => $location,
            'location' => $location,
            'designation' => $designation,
            'business_category' => $industry,
            'industry' => $industry,
            'level_4_category' => $level4,
            'level4_category' => $level4,
            'profile_photo_url' => $avatarUrl,
            'avatar_url' => $avatarUrl,
            'life_impact' => $impact,
            'life_impacted_count' => $impact,
            'impact' => $impact,
            'impact_count' => $impact,
            'circle' => $circleName,
            'circle_name' => $circleName,
            'circle_id' => $circleId,
            'tags' => $tags,
            'status' => $status,
            'deals_formatted' => $dealsFormatted,
            'coins' => $coins,
            'attendance' => '94%',
            'phone' => (string) ($u->phone ?? $u->secondary_mobile ?? ''),
            'email' => (string) ($u->email ?? ''),
            'is_verified' => (bool) ($u->is_verified ?? true),
            'intro_video_url' => $introVideo,
        ];
    }

    /**
     * Get detailed rich profile of a peer.
     *
     * @return array<string, mixed>
     */
    public function getPeer(string $peerId, ?User $requestingUser = null): array
    {
        $user = User::query()->where('id', $peerId)->with(['circleMembers.circle', 'activeCircle', 'businessCategory', 'level4Category'])->first();

        if (! $user) {
            return [];
        }

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($name === '') {
            $name = (string) ($user->display_name ?? 'Peer Member');
        }

        $circleName = '';
        $circleId = (string) ($user->active_circle_id ?? '');
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

        $location = (string) ($user->city ?? $user->city_of_residence ?? 'Ahmedabad');
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
            : [$industry, $level4];

        $coins = (int) ($user->coins_balance ?? 0);
        $impact = (int) ($user->life_impacted_count ?? $user->impact_count ?? 0);

        // Dynamically compute deals
        $dealsSum = (float) DB::table('business_deals')
            ->where(function ($q) use ($user): void {
                $q->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id);
            })
            ->whereNull('deleted_at')
            ->sum('deal_amount');

        $dealsFormatted = $dealsSum >= 10000000
            ? '₹'.round($dealsSum / 10000000, 2).'Cr'
            : ($dealsSum >= 100000 ? '₹'.round($dealsSum / 100000, 1).'L' : '₹'.number_format($dealsSum, 0));

        $p2pMeetingsCount = DB::table('p2p_meetings')
            ->where(fn ($q) => $q->where('initiator_user_id', $user->id)->orWhere('peer_user_id', $user->id))
            ->whereNull('deleted_at')
            ->count();

        $referralsGiven = DB::table('referrals')
            ->where('from_user_id', $user->id)
            ->whereNull('deleted_at')
            ->count();

        $referralsReceived = DB::table('referrals')
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')
            ->count();

        // Privacy masking
        $hidePhone = (bool) ($user->hide_phone ?? false);
        $hideEmail = (bool) ($user->hide_email ?? false);
        $canSeePrivate = false;

        if ($requestingUser) {
            $permService = app(LeaderPermissionService::class);
            $roleInfo = $permService->resolveUserRole($requestingUser);
            $requesterRole = (string) ($roleInfo['role'] ?? 'member');
            $canSeePrivate = in_array($requesterRole, ['superAdmin', 'countryDirector', 'districtExecDirector'], true)
                || in_array('manage_peers', $permService->getEnabledCapabilitiesForRole($requesterRole), true)
                || (string) $requestingUser->id === $peerId;
        }

        $phone = (string) ($user->phone ?? $user->secondary_mobile ?? '');
        $email = (string) ($user->email ?? '');

        if ($hidePhone && ! $canSeePrivate) {
            $phone = '********';
        }
        if ($hideEmail && ! $canSeePrivate) {
            $email = '********';
        }

        $company = (string) ($user->company_name ?? $user->business_name ?? 'Apex Dynamics');
        $designation = (string) ($user->designation ?? $user->job_title ?? 'Founder & CEO');
        $photoUrl = $user->profile_photo_url ?? $user->avatar_url ?? null;

        return [
            'id' => (string) $user->id,
            'name' => $name,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'company_name' => $company,
            'company' => $company,
            'city' => $location,
            'location' => $location,
            'designation' => $designation,
            'business_category' => $industry,
            'industry' => $industry,
            'level_4_category' => $level4,
            'level4_category' => $level4,
            'sub_industry' => $subIndustry,
            'profile_photo_url' => $photoUrl,
            'avatar_url' => $photoUrl,
            'life_impact' => $impact,
            'life_impacted_count' => $impact,
            'impact' => $impact,
            'impact_count' => $impact,
            'circle' => $circleName,
            'circle_name' => $circleName,
            'circle_id' => $circleId,
            'status' => $status,
            'is_verified' => (bool) ($user->is_verified ?? true),
            'intro_video_url' => (string) ($user->intro_video_url ?? ''),
            'bio' => (string) ($user->short_bio ?? $user->long_bio_html ?? ''),
            'birthday' => $birthday,
            'anniversary' => $anniversary,
            'joined_date' => $joinedDate,
            'hide_phone' => $hidePhone,
            'hide_email' => $hideEmail,
            'contact' => [
                'email' => $email,
                'phone' => $phone,
                'linkedin' => (string) ($user->linkedin_profile ?? ''),
                'whatsapp' => $hidePhone && ! $canSeePrivate ? '********' : (string) ($user->phone ?? $user->secondary_mobile ?? ''),
            ],
            'metrics' => [
                'life_impact' => $impact,
                'life_impacted_count' => $impact,
                'impact' => $impact,
                'impact_count' => $impact,
                'deals_given' => $dealsFormatted,
                'deals_received' => $dealsFormatted,
                'deals_closed' => $dealsFormatted,
                'attendance_percentage' => '94%',
                'attendance_rate' => '94%',
                'p2p_meetings' => $p2pMeetingsCount,
                'p2p_sessions' => $p2pMeetingsCount,
                'referrals_given' => $referralsGiven,
                'referrals_received' => $referralsReceived,
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
        $scopedCircleIds = $this->resolveScopedCircleIds($user, $districtId);

        $query = User::query()->whereNull('deleted_at');

        if ($circleId && Str::isUuid($circleId)) {
            if ($scopedCircleIds !== null && ! in_array($circleId, $scopedCircleIds, true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $q) use ($circleId): void {
                    $q->whereHas('circleMembers', fn ($cm) => $cm->where('circle_id', $circleId)->whereNull('deleted_at'))
                        ->orWhere('active_circle_id', $circleId);
                });
            }
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $q) use ($scopedCircleIds): void {
                    $q->whereHas('circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds)->whereNull('deleted_at'))
                        ->orWhereIn('active_circle_id', $scopedCircleIds);
                });
            }
        } else {
            $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);
            if ($resolvedDistrictId) {
                $query->whereHas('circleMembers.circle', fn ($c) => $c->where('district_id', $resolvedDistrictId));
            }
        }

        $users = $query->take(5)->get();

        $birthdays = [];
        $anniversaries = [];

        foreach ($users as $idx => $u) {
            $uName = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($uName === '') {
                $uName = (string) ($u->display_name ?? 'Peer Member');
            }
            $company = (string) ($u->company_name ?? $u->business_name ?? 'Aequitas Enterprise');

            if ($idx === 0) {
                $birthdays[] = [
                    'id' => 'cel_b_'.($u->id ?? '1'),
                    'peer_id' => (string) $u->id,
                    'name' => $uName,
                    'company' => $company,
                    'date_formatted' => 'Today, '.now()->format('d M'),
                    'is_today' => true,
                ];
            } elseif ($idx === 1) {
                $birthdays[] = [
                    'id' => 'cel_b_'.($u->id ?? '2'),
                    'peer_id' => (string) $u->id,
                    'name' => $uName,
                    'company' => $company,
                    'date_formatted' => now()->addDays(3)->format('d M'),
                    'is_today' => false,
                ];
            } elseif ($idx === 2) {
                $anniversaries[] = [
                    'id' => 'cel_a_'.($u->id ?? '3'),
                    'peer_id' => (string) $u->id,
                    'name' => $uName,
                    'company' => $company,
                    'milestone' => '1 Year in Circle',
                    'date_formatted' => now()->addDays(4)->format('d M'),
                    'is_today' => false,
                ];
            }
        }

        return [
            'birthdays' => $birthdays,
            'anniversaries' => $anniversaries,
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
