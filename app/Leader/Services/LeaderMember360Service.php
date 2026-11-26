<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Leader\Models\LeaderReport;
use App\Models\ActivityCreative;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Finance\FinanceLeaderTenure;
use App\Models\Impact;
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

        if (! $member || $member->isSystemUser()) {
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

        $member = User::query()
            ->with([
                'city',
                'businessCategory',
                'level4Category',
                'introducedBy',
                'circleMembers.circle',
                'circleMembers.level1Category',
                'circleMembers.level4Category',
                'activeCircle',
            ])
            ->find($memberId);

        if ($member) {
            $sections = $this->buildFounderProfileSections($member, $leader, $profile);
            $profile = array_merge($profile, $sections);
        }

        return $profile;
    }

    /**
     * Build complete data-driven Founder-level sections for a Peer Profile.
     *
     * @param  array<string, mixed>  $baseProfile
     * @return array<string, mixed>
     */
    public function buildFounderProfileSections(User $member, User|AdminUser|null $leader, array $baseProfile): array
    {
        $memberId = (string) $member->id;
        $leaderRoleInfo = $leader ? $this->permissionService->resolveUserRole($leader) : ['role' => 'guest'];
        $leaderRole = (string) ($leaderRoleInfo['role'] ?? 'guest');
        $leaderCaps = ($leader instanceof AdminUser)
            ? ['access_dashboard', 'access_teams', 'access_finance', 'regional_data', 'view_peers', 'manage_peers', 'request_actions', 'view_reports', 'manage_finance', 'coin_payouts', 'manage_roles', 'system_configs']
            : ($leader ? $this->permissionService->getEnabledCapabilitiesForRole($leaderRole) : []);
        $isSuperAdmin = ($leaderRole === 'superAdmin');

        $canViewFinance = $isSuperAdmin || in_array('access_finance', $leaderCaps, true) || in_array('manage_finance', $leaderCaps, true);
        $canManagePeers = $isSuperAdmin || in_array('manage_peers', $leaderCaps, true);
        $canViewCompliance = $isSuperAdmin
            || in_array($leaderRole, ['globalFounder', 'countryDirector', 'districtExecDirector', 'circleFounder', 'circleDirector'], true)
            || $canManagePeers
            || $canViewFinance;
        $canModerateImpacts = false;
        if ($leader && (! ($leader instanceof User) || (string) $leader->id !== $memberId)) {
            $canModerateImpacts = in_array($leaderRole, ['superAdmin', 'districtExecDirector'], true);
            if ($canModerateImpacts && $leaderRole === 'districtExecDirector') {
                $dedCircles = $this->peersService->resolveScopedCircleIds($leader) ?? [];
                $memberCircles = $member->circleMembers->pluck('circle_id')->map(fn ($id) => (string) $id)->all();
                if (! empty($member->active_circle_id)) {
                    $memberCircles[] = (string) $member->active_circle_id;
                }
                $canModerateImpacts = ! empty(array_intersect($dedCircles, $memberCircles));
            }
        }

        // Batch-load all circles and districts this peer is linked to
        $circleIds = $member->circleMembers
            ->pluck('circle_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();
        if (! empty($member->active_circle_id)) {
            $circleIds[] = (string) $member->active_circle_id;
        }
        $circleIds = array_values(array_unique($circleIds));

        $circlesById = ! empty($circleIds)
            ? Circle::query()->whereIn('id', $circleIds)->whereNull('deleted_at')->get()->keyBy(fn ($c) => (string) $c->id)
            : collect();

        $districtIds = $circlesById->pluck('district_id')->filter()->map(fn ($d) => (string) $d)->unique()->values()->all();
        $districtsById = (! empty($districtIds) && Schema::hasTable('districts'))
            ? DB::table('districts')->whereIn('id', $districtIds)->pluck('name', 'id')->all()
            : [];

        $primaryCircle = (! empty($member->active_circle_id) && $circlesById->has((string) $member->active_circle_id))
            ? $circlesById->get((string) $member->active_circle_id)
            : $circlesById->first();

        $primaryDistrictId = $primaryCircle && ! empty($primaryCircle->district_id) ? (string) $primaryCircle->district_id : null;
        $primaryDistrictName = $primaryDistrictId ? ($districtsById[$primaryDistrictId] ?? ($member->district ?? null)) : ($member->district ?? null);

        // Batch-load assigned leaders on primary circle (no N+1)
        $assignedLeadership = [];
        $currentPositions = [];
        $historicalPositions = [];
        $positionDedupe = [];
        $addPosition = static function (array &$target, array &$dedupe, array $pos): void {
            $key = strtolower(($pos['role_key'] ?? '').'|'.($pos['circle_id'] ?? '').'|'.($pos['started_at'] ?? '').'|'.($pos['ended_at'] ?? ''));
            if (isset($dedupe[$key])) {
                return;
            }
            $dedupe[$key] = true;
            $target[] = $pos;
        };

        $leadershipColumns = [
            'circle_founder_user_id' => ['key' => 'circleFounder', 'label' => 'Circle Founder'],
            'circle_director_user_id' => ['key' => 'circleDirector', 'label' => 'Circle Director'],
            'chair_user_id' => ['key' => 'circleChair', 'label' => 'Circle Chair'],
            'vice_chair_user_id' => ['key' => 'viceChair', 'label' => 'Vice Chair'],
            'secretary_user_id' => ['key' => 'secretary', 'label' => 'Secretary'],
            'industry_director_user_id' => ['key' => 'industryDirector', 'label' => 'Industry Director'],
            'ded_user_id' => ['key' => 'districtExecDirector', 'label' => 'District Executive Director'],
        ];

        $leaderUserIdsToFetch = [];
        if ($primaryCircle) {
            foreach (array_keys($leadershipColumns) as $col) {
                if (! empty($primaryCircle->{$col})) {
                    $leaderUserIdsToFetch[] = (string) $primaryCircle->{$col};
                }
            }
        }
        if ($member->introduced_by) {
            $leaderUserIdsToFetch[] = (string) $member->introduced_by;
        }
        $leaderUserIdsToFetch = array_values(array_unique(array_filter($leaderUserIdsToFetch)));
        $resolvedLeaderUsers = ! empty($leaderUserIdsToFetch)
            ? User::query()->whereIn('id', $leaderUserIdsToFetch)->get(['id', 'first_name', 'last_name', 'display_name', 'company_name', 'designation'])->keyBy(fn ($u) => (string) $u->id)
            : collect();

        $resolveUserLabel = static function (?User $u): string {
            if (! $u) {
                return '';
            }
            $n = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));

            return $n !== '' ? $n : (string) ($u->display_name ?? '');
        };

        if ($primaryCircle) {
            foreach ($leadershipColumns as $col => $meta) {
                $uid = (string) ($primaryCircle->{$col} ?? '');
                if ($uid !== '' && $resolvedLeaderUsers->has($uid)) {
                    $lu = $resolvedLeaderUsers->get($uid);
                    $assignedLeadership[] = [
                        'role_key' => $meta['key'],
                        'role_label' => $meta['label'],
                        'user_id' => $uid,
                        'name' => $resolveUserLabel($lu),
                        'company_name' => (string) ($lu->company_name ?? ''),
                    ];
                }
            }
        }

        // Check ALL circles where this peer holds a leadership position
        $circleLeadershipQuery = Circle::query()->whereNull('deleted_at')->where(function ($q) use ($memberId): void {
            $q->where('circle_founder_user_id', $memberId)
                ->orWhere('circle_director_user_id', $memberId)
                ->orWhere('chair_user_id', $memberId)
                ->orWhere('vice_chair_user_id', $memberId)
                ->orWhere('secretary_user_id', $memberId)
                ->orWhere('industry_director_user_id', $memberId)
                ->orWhere('ded_user_id', $memberId);
        })->get();

        foreach ($circleLeadershipQuery as $lc) {
            foreach ($leadershipColumns as $col => $meta) {
                if ((string) ($lc->{$col} ?? '') === $memberId) {
                    $addPosition($currentPositions, $positionDedupe, [
                        'role_key' => $meta['key'],
                        'role_label' => $meta['label'],
                        'scope_type' => 'circle',
                        'circle_id' => (string) $lc->id,
                        'circle_name' => (string) $lc->name,
                        'started_at' => $lc->launch_date ? $lc->launch_date->toDateString() : ($lc->created_at ? $lc->created_at->toDateString() : null),
                        'ended_at' => null,
                        'status' => 'active',
                        'is_current' => true,
                    ]);
                }
            }
        }

        // Check circle_members for non-ordinary leadership roles ONLY (never treat 'member' / 'peer' as leadership)
        foreach ($member->circleMembers as $cm) {
            $rawRole = strtolower(trim((string) ($cm->role ?? '')));
            if ($rawRole !== '' && ! in_array($rawRole, ['member', 'peer', 'guest', 'visitor'], true)) {
                $cModel = $circlesById->get((string) $cm->circle_id);
                $isActiveCm = empty($cm->left_at) && in_array(strtolower((string) ($cm->status ?? 'active')), ['active', 'approved', ''], true);
                $roleLabel = ucwords(str_replace(['_', '-'], ' ', $rawRole));
                $pos = [
                    'role_key' => $rawRole,
                    'role_label' => $roleLabel,
                    'scope_type' => 'circle',
                    'circle_id' => (string) ($cm->circle_id ?? ''),
                    'circle_name' => $cModel ? (string) $cModel->name : '',
                    'started_at' => $cm->joined_at ? $cm->joined_at->toDateString() : ($cm->created_at ? $cm->created_at->toDateString() : null),
                    'ended_at' => $cm->left_at ? $cm->left_at->toDateString() : null,
                    'status' => $isActiveCm ? 'active' : 'completed',
                    'is_current' => $isActiveCm,
                ];
                if ($isActiveCm) {
                    $addPosition($currentPositions, $positionDedupe, $pos);
                } else {
                    $addPosition($historicalPositions, $positionDedupe, $pos);
                }
            }
        }

        // Check FinanceLeaderTenure for historical and active tenures
        if (Schema::hasTable('finance_leader_tenures')) {
            $tenures = FinanceLeaderTenure::query()
                ->with('role')
                ->where('user_id', $memberId)
                ->orderByDesc('tenure_start_at')
                ->get();

            foreach ($tenures as $t) {
                $rKey = (string) ($t->role_key ?? $t->role?->key ?? '');
                $rLabel = (string) ($t->role?->name ?? ucwords(str_replace(['_', '-'], ' ', $rKey)));
                if ($rLabel === '' || in_array(strtolower($rKey), ['member', 'peer'], true)) {
                    continue;
                }
                $isCurrent = (bool) $t->is_active && $t->tenure_end_at === null;
                $pos = [
                    'role_key' => $rKey,
                    'role_label' => $rLabel,
                    'scope_type' => (string) ($t->scope_type ?? ''),
                    'circle_id' => (string) ($t->scope_id ?? ''),
                    'circle_name' => (string) ($t->scope_name_snapshot ?? ''),
                    'started_at' => $t->tenure_start_at ? $t->tenure_start_at->toDateString() : null,
                    'ended_at' => $t->tenure_end_at ? $t->tenure_end_at->toDateString() : null,
                    'status' => $isCurrent ? 'active' : 'completed',
                    'is_current' => $isCurrent,
                    'removal_reason' => $t->removal_reason ?: null,
                ];
                if ($isCurrent) {
                    $addPosition($currentPositions, $positionDedupe, $pos);
                } else {
                    $addPosition($historicalPositions, $positionDedupe, $pos);
                }
            }
        }

        // Leadership reports submitted by this peer
        $reportsSubmittedCount = 0;
        $recentReports = [];
        if (Schema::hasTable('leader_reports')) {
            $reportsQuery = LeaderReport::query()
                ->with('circle:id,name')
                ->where('submitted_by_user_id', $memberId)
                ->whereNull('deleted_at');
            $reportsSubmittedCount = (clone $reportsQuery)->count();
            $recentReports = $reportsQuery
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (LeaderReport $r): array => [
                    'id' => (string) $r->id,
                    'report_type' => (string) ($r->report_type ?? 'Report'),
                    'period' => (string) ($r->period ?? ''),
                    'circle_name' => (string) ($r->circle?->name ?? ''),
                    'status' => (string) ($r->status ?? 'submitted'),
                    'submitted_at' => $r->created_at ? $r->created_at->toIso8601String() : null,
                ])
                ->all();
        }

        // Leadership certifications
        $leadershipCertifications = [];
        if (Schema::hasTable('leadership_certification_submissions')) {
            $leadershipCertifications = DB::table('leadership_certification_submissions')
                ->where('user_id', $memberId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($row): array => [
                    'id' => (string) ($row->id ?? ''),
                    'title' => (string) ($row->certification_title ?? $row->type ?? 'Leadership Certification'),
                    'status' => ucfirst((string) ($row->status ?? 'submitted')),
                    'date' => (string) ($row->created_at ?? ''),
                ])
                ->all();
        }

        // Business profile completeness calculation from real non-empty business attributes
        $businessFieldsForCompleteness = [
            $member->company_name ?? $member->business_name,
            $member->designation,
            $member->businessCategory?->name ?? $member->industry,
            $member->level4Category?->name ?? $member->business_sub_category,
            $member->short_bio ?? $member->experience_summary,
            $member->products_services_offered,
            $member->business_website ?? $member->website,
            $member->business_city ?? $member->city,
        ];
        $filledBizCount = count(array_filter($businessFieldsForCompleteness, fn ($v) => ! empty(trim((string) ($v ?? '')))));
        $completenessPercent = (int) round(($filledBizCount / count($businessFieldsForCompleteness)) * 100);

        $stringList = static function (mixed $val): array {
            if (is_array($val)) {
                return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $val)));
            }
            if (is_string($val) && trim($val) !== '') {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $decoded)));
                }

                return array_values(array_filter(array_map('trim', explode(',', $val))));
            }

            return [];
        };

        $yearsInBusiness = $member->experience_years;
        if (empty($yearsInBusiness) && ! empty($member->year_of_establishment) && (int) $member->year_of_establishment > 1900) {
            $yearsInBusiness = max(0, (int) date('Y') - (int) $member->year_of_establishment);
        }

        // Business & Collaboration activity
        $dealsGivenCount = 0;
        $dealsReceivedCount = 0;
        $crossCircleDealsCount = 0;
        $recentDeals = [];
        if (Schema::hasTable('business_deals')) {
            $allDeals = DB::table('business_deals as bd')
                ->leftJoin('users as fu', 'fu.id', '=', 'bd.from_user_id')
                ->leftJoin('users as tu', 'tu.id', '=', 'bd.to_user_id')
                ->where(function ($q) use ($memberId): void {
                    $q->where('bd.from_user_id', $memberId)
                        ->orWhere('bd.to_user_id', $memberId);
                })
                ->whereNull('bd.deleted_at')
                ->orderByDesc('bd.created_at')
                ->select([
                    'bd.id',
                    'bd.from_user_id',
                    'bd.to_user_id',
                    'bd.deal_amount',
                    'bd.business_type',
                    'bd.deal_date',
                    'bd.comment',
                    'bd.created_at',
                    'fu.first_name as from_first',
                    'fu.last_name as from_last',
                    'fu.active_circle_id as from_circle_id',
                    'tu.first_name as to_first',
                    'tu.last_name as to_last',
                    'tu.active_circle_id as to_circle_id',
                ])
                ->get();

            foreach ($allDeals as $d) {
                $isGiver = (string) ($d->from_user_id ?? '') === $memberId;
                if ($isGiver) {
                    $dealsGivenCount++;
                } else {
                    $dealsReceivedCount++;
                }
                if (! empty($d->from_circle_id) && ! empty($d->to_circle_id) && (string) $d->from_circle_id !== (string) $d->to_circle_id) {
                    $crossCircleDealsCount++;
                }
                if (count($recentDeals) < 10) {
                    $counterpartName = $isGiver
                        ? trim(($d->to_first ?? '').' '.($d->to_last ?? ''))
                        : trim(($d->from_first ?? '').' '.($d->from_last ?? ''));
                    $amt = (float) ($d->deal_amount ?? 0);
                    $recentDeals[] = [
                        'id' => (string) $d->id,
                        'direction' => $isGiver ? 'given' : 'received',
                        'counterpart_name' => $counterpartName !== '' ? $counterpartName : 'Peer',
                        'amount' => $amt,
                        'amount_formatted' => '₹'.number_format($amt, 0),
                        'business_type' => (string) ($d->business_type ?? 'Business Deal'),
                        'deal_date' => $d->deal_date ? (string) $d->deal_date : ($d->created_at ? Carbon::parse($d->created_at)->toDateString() : ''),
                        'notes' => (string) ($d->comment ?? ''),
                    ];
                }
            }
        }

        // Asks counts separated strictly from completed deals
        $collabAsksCount = 0;
        $referralAsksCount = 0;
        $helpAsksCount = 0;
        $recentAsks = [];
        if (Schema::hasTable('asks') && Schema::hasTable('ask_flows')) {
            $askRows = DB::table('asks as a')
                ->leftJoin('ask_flows as af', 'af.id', '=', 'a.flow_id')
                ->where('a.user_id', $memberId)
                ->whereNull('a.deleted_at')
                ->orderByDesc('a.created_at')
                ->select(['a.id', 'a.title', 'a.status', 'a.created_at', 'af.code as flow_code', 'af.name as flow_name'])
                ->get();

            foreach ($askRows as $ar) {
                $code = strtolower((string) ($ar->flow_code ?? ''));
                if ($code === 'collaboration') {
                    $collabAsksCount++;
                } elseif ($code === 'referral') {
                    $referralAsksCount++;
                } elseif ($code === 'help') {
                    $helpAsksCount++;
                }
                if (count($recentAsks) < 8) {
                    $recentAsks[] = [
                        'id' => (string) $ar->id,
                        'flow_code' => $code,
                        'flow_name' => (string) ($ar->flow_name ?? ucfirst($code)),
                        'title' => (string) ($ar->title ?? 'Business Ask'),
                        'status' => ucfirst((string) ($ar->status ?? 'open')),
                        'created_at' => $ar->created_at ? Carbon::parse($ar->created_at)->toIso8601String() : null,
                    ];
                }
            }
        }

        // Life Impact Submissions breakdown (real status + permission-aware details)
        $impactPendingCount = 0;
        $impactApprovedCount = 0;
        $impactRejectedCount = 0;
        $impactCategoriesMap = [];
        $impactSubmissions = [];
        $activitiesService = app(LeaderActivitiesService::class);

        if (Schema::hasTable('impacts')) {
            $impactsQuery = Impact::query()
                ->with(['user', 'impactedPeer.circleMembers.circle', 'approvedBy', 'approvedByUser', 'rejectedBy', 'rejectedByUser'])
                ->where('user_id', $memberId);
            if (Schema::hasColumn('impacts', 'deleted_at')) {
                $impactsQuery->whereNull('deleted_at');
            }
            $allImpacts = $impactsQuery->orderByDesc('created_at')->get();

            foreach ($allImpacts as $imp) {
                $st = strtolower(trim((string) ($imp->status ?? 'approved')));
                if ($st === 'pending') {
                    $impactPendingCount++;
                } elseif ($st === 'rejected') {
                    $impactRejectedCount++;
                } else {
                    $st = 'approved';
                    $impactApprovedCount++;
                }

                $catName = trim((string) ($imp->action ?? 'Life Impact'));
                $impactCategoriesMap[$catName] = ($impactCategoriesMap[$catName] ?? 0) + 1;

                // Only expose non-approved submissions or private attachments if viewer can moderate or manage peers
                if ($st === 'approved' || $canModerateImpacts || $canManagePeers) {
                    $impactSubmissions[] = $activitiesService->formatImpactRecord(
                        $imp,
                        $leader,
                        includeHistory: false,
                        includeAttachments: ($canModerateImpacts || $canManagePeers || $st === 'approved')
                    );
                }
            }
        }

        // Circle Attendance & Events Participation
        $circleAttendance = ['attended' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total_recorded' => 0, 'attendance_rate_percent' => null];
        if (Schema::hasTable('attendance_records')) {
            $attRows = DB::table('attendance_records')->where('user_id', $memberId)->get(['status']);
            foreach ($attRows as $ar) {
                $circleAttendance['total_recorded']++;
                $st = strtolower(trim((string) ($ar->status ?? '')));
                if (in_array($st, ['present', 'attended'], true)) {
                    $circleAttendance['attended']++;
                } elseif ($st === 'late') {
                    $circleAttendance['late']++;
                } elseif ($st === 'excused') {
                    $circleAttendance['excused']++;
                } else {
                    $circleAttendance['absent']++;
                }
            }
            if ($circleAttendance['total_recorded'] > 0) {
                $attendedPositive = $circleAttendance['attended'] + $circleAttendance['late'];
                $circleAttendance['attendance_rate_percent'] = round(($attendedPositive / $circleAttendance['total_recorded']) * 100, 1);
            }
        }

        $badgesResult = $this->getMemberBadges($memberId, ['per_page' => 20]);
        $eventsResult = $this->getMemberEventRegistrations($memberId, ['per_page' => 10]);

        $primaryRoleTitle = ! empty($currentPositions) ? (string) $currentPositions[0]['role_label'] : null;

        // Membership renewal & compliance data (permission-protected)
        $subs = $baseProfile['subscriptions'] ?? [];
        $activeAppSub = is_array($subs) ? ($subs['active_app_subscription'] ?? null) : null;
        $complianceData = ['is_visible' => false];
        if ($canViewCompliance) {
            $pendingActions = [];
            if ($impactPendingCount > 0 && ($canModerateImpacts || $isSuperAdmin)) {
                $pendingActions[] = "{$impactPendingCount} Life Impact submission(s) pending verification";
            }
            if (strtolower((string) ($member->approval_status ?? '')) === 'pending') {
                $pendingActions[] = 'Account approval pending';
            }

            $sanitizedSub = null;
            if (is_array($activeAppSub)) {
                $sanitizedSub = [
                    'plan_name' => $activeAppSub['plan_name'] ?? null,
                    'status' => $activeAppSub['status'] ?? null,
                    'starts_at' => $activeAppSub['starts_at'] ?? null,
                    'ends_at' => $activeAppSub['ends_at'] ?? null,
                    'days_left' => $activeAppSub['days_left'] ?? null,
                ];
                if ($canViewFinance) {
                    $sanitizedSub['price'] = $activeAppSub['price'] ?? null;
                    $sanitizedSub['payment_status'] = $activeAppSub['payment_status'] ?? null;
                }
            }

            $complianceData = [
                'is_visible' => true,
                'account_status' => (string) ($baseProfile['status'] ?? 'Active'),
                'approval_status' => $member->approval_status ? ucfirst((string) $member->approval_status) : null,
                'membership_status' => $member->membership_status ? (string) $member->membership_status : ($activeAppSub['status'] ?? null),
                'renewal_status' => $activeAppSub['status'] ?? null,
                'starts_at' => $member->membership_starts_at ? $member->membership_starts_at->toDateString() : ($activeAppSub['starts_at'] ?? null),
                'expires_at' => $member->membership_ends_at ? $member->membership_ends_at->toDateString() : ($activeAppSub['ends_at'] ?? null),
                'days_remaining' => $activeAppSub['days_left'] ?? null,
                'pending_actions' => $pendingActions,
                'verification_flags' => [
                    'is_verified' => (bool) ($member->is_verified ?? false),
                    'has_profile_photo' => ! empty($baseProfile['profile_photo_url']),
                    'has_company_info' => ! empty($baseProfile['company_name']),
                    'has_bio' => ! empty($baseProfile['bio']),
                ],
                'admin_remarks' => $canManagePeers ? ($member->membership_expiry_date_remark ?: null) : null,
                'subscription_summary' => $sanitizedSub,
            ];
        }

        // Build real chronological timeline from actual records
        $timeline = [];
        if ($member->created_at) {
            $timeline[] = [
                'id' => 'joined_'.$memberId,
                'event_type' => 'peer_joined',
                'title' => 'Joined Peers Global Unity',
                'subtitle' => $primaryCircle ? (string) $primaryCircle->name : 'Platform Membership',
                'status' => 'Completed',
                'date' => $member->created_at->toIso8601String(),
                'reference_type' => null,
                'reference_id' => null,
            ];
        }
        foreach ($currentPositions as $cp) {
            if (! empty($cp['started_at'])) {
                $timeline[] = [
                    'id' => 'role_'.md5(($cp['role_key'] ?? '').($cp['circle_id'] ?? '')),
                    'event_type' => 'leadership_appointment',
                    'title' => 'Appointed as '.($cp['role_label'] ?? 'Leader'),
                    'subtitle' => $cp['circle_name'] ?? '',
                    'status' => 'Active',
                    'date' => Carbon::parse($cp['started_at'])->toIso8601String(),
                    'reference_type' => 'circle',
                    'reference_id' => $cp['circle_id'] ?? null,
                ];
            }
        }
        foreach (array_slice($impactSubmissions, 0, 10) as $impItem) {
            if (! empty($impItem['submitted_at'])) {
                $timeline[] = [
                    'id' => 'impact_'.$impItem['id'],
                    'event_type' => 'life_impact',
                    'title' => 'Life Impact: '.($impItem['title'] ?: 'Impact Record'),
                    'subtitle' => ($impItem['life_impacted'] ?? 1).' life impacted',
                    'status' => $impItem['status_label'] ?? 'Pending',
                    'date' => $impItem['submitted_at'],
                    'reference_type' => 'life_impact',
                    'reference_id' => $impItem['id'],
                ];
            }
        }
        foreach (array_slice($recentDeals, 0, 6) as $dealItem) {
            if (! empty($dealItem['deal_date'])) {
                $timeline[] = [
                    'id' => 'deal_'.$dealItem['id'],
                    'event_type' => 'business_deal',
                    'title' => 'Business Deal '.$dealItem['amount_formatted'],
                    'subtitle' => ($dealItem['direction'] === 'given' ? 'With ' : 'From ').$dealItem['counterpart_name'],
                    'status' => 'Closed',
                    'date' => Carbon::parse($dealItem['deal_date'])->toIso8601String(),
                    'reference_type' => 'business_deal',
                    'reference_id' => $dealItem['id'],
                ];
            }
        }
        foreach (array_slice($recentAsks, 0, 5) as $askItem) {
            if (! empty($askItem['created_at'])) {
                $timeline[] = [
                    'id' => 'ask_'.$askItem['id'],
                    'event_type' => 'collaboration_ask',
                    'title' => $askItem['flow_name'].': '.$askItem['title'],
                    'subtitle' => 'Business Ask',
                    'status' => $askItem['status'],
                    'date' => $askItem['created_at'],
                    'reference_type' => 'ask',
                    'reference_id' => $askItem['id'],
                ];
            }
        }
        usort($timeline, fn ($a, $b) => strtotime((string) ($b['date'] ?? '0')) <=> strtotime((string) ($a['date'] ?? '0')));

        $introducedByUser = $member->introduced_by ? $resolvedLeaderUsers->get((string) $member->introduced_by) : null;

        return [
            'identity' => [
                'id' => $memberId,
                'peer_code' => $baseProfile['peer_code'] ?? ('PG-'.strtoupper(substr(str_replace('-', '', $memberId), 0, 8))),
                'full_name' => $baseProfile['name'] ?? '',
                'first_name' => $baseProfile['first_name'] ?? '',
                'last_name' => $baseProfile['last_name'] ?? '',
                'display_name' => (string) ($member->display_name ?? $baseProfile['name'] ?? ''),
                'profile_photo_url' => $baseProfile['profile_photo_url'] ?? null,
                'cover_photo_url' => $member->cover_photo_url ?? null,
                'designation' => $baseProfile['designation'] ?? '',
                'company_name' => $baseProfile['company_name'] ?? '',
                'primary_category' => $baseProfile['business_category'] ?? '',
                'sub_category' => $baseProfile['sub_industry'] ?? '',
                'level4_category' => $baseProfile['level4_category'] ?? '',
                'city' => $baseProfile['city'] ?? '',
                'state' => (string) ($member->state ?? $member->business_state ?? ''),
                'country' => (string) ($member->country ?? $member->business_country ?? ''),
                'district_id' => $primaryDistrictId,
                'district_name' => $primaryDistrictName,
                'circle_id' => $primaryCircle ? (string) $primaryCircle->id : ($baseProfile['circle_id'] ?? ''),
                'circle_name' => $primaryCircle ? (string) $primaryCircle->name : ($baseProfile['circle_name'] ?? ''),
                'membership_status' => $member->membership_status ? (string) $member->membership_status : ($baseProfile['status'] ?? 'Active'),
                'account_status' => $baseProfile['status'] ?? 'Active',
                'approval_status' => $member->approval_status ? (string) $member->approval_status : null,
                'is_verified' => (bool) ($member->is_verified ?? false),
                'is_online' => (bool) ($member->is_online ?? false),
                'last_seen_at' => $member->last_seen_at ? $member->last_seen_at->toIso8601String() : null,
                'current_leadership_role' => $primaryRoleTitle,
                'contact_authorized' => (bool) ($baseProfile['contact_authorized'] ?? true),
                'contact' => $baseProfile['contact'] ?? [],
            ],
            'business' => [
                'company_name' => (string) ($member->company_name ?? $member->business_name ?? ''),
                'company_type' => (string) ($member->company_type ?? ''),
                'designation' => (string) ($member->designation ?? ''),
                'short_bio' => (string) ($member->short_bio ?? ''),
                'experience_summary' => (string) ($member->experience_summary ?? ''),
                'experience_years' => $member->experience_years !== null ? (int) $member->experience_years : null,
                'year_of_establishment' => $member->year_of_establishment !== null ? (int) $member->year_of_establishment : null,
                'years_in_business' => $yearsInBusiness !== null ? (int) $yearsInBusiness : null,
                'industry' => (string) ($member->businessCategory?->name ?? $member->industry ?? ''),
                'sub_industry' => (string) ($member->business_sub_category ?? ''),
                'level4_category' => (string) ($member->level4Category?->name ?? ''),
                'business_categories' => array_values(array_filter([
                    $member->businessCategory?->name,
                    $member->business_sub_category,
                    $member->level4Category?->name,
                ])),
                'industry_tags' => $stringList($member->industry_tags),
                'products_services_offered' => (string) ($member->products_services_offered ?? ''),
                'number_of_employees' => $member->number_of_employees ? (string) $member->number_of_employees : '',
                'annual_revenue_range' => ($canViewFinance || $canManagePeers) ? (string) ($member->annual_revenue_range ?? $member->turnover_range ?? '') : '',
                'website' => (string) ($member->business_website ?? $member->website ?? $member->other_website ?? ''),
                'business_address' => (string) ($member->business_address ?? ''),
                'business_city' => (string) ($member->business_city ?? $member->city ?? ''),
                'business_state' => (string) ($member->business_state ?? $member->state ?? ''),
                'business_country' => (string) ($member->business_country ?? $member->country ?? ''),
                'business_pincode' => (string) ($member->business_pincode ?? ''),
                'skills' => $stringList($member->skills),
                'superpower' => (string) ($member->superpower ?? ''),
                'i_can_help_with' => $stringList($member->i_can_help_with),
                'i_am_looking_for' => $stringList($member->i_am_looking_for),
                'business_keywords' => $stringList($member->business_keywords),
                'target_regions' => $stringList($member->target_regions),
                'target_business_categories' => $stringList($member->target_business_categories),
                'industries_of_interest' => $stringList($member->industries_of_interest),
                'collaboration_goals' => $stringList($member->collaboration_goals),
                'preferred_meeting_format' => (string) ($member->preferred_meeting_format ?? ''),
                'willing_to_mentor' => (bool) ($member->willing_to_mentor ?? false),
                'open_to_cross_city_collaboration' => (bool) ($member->open_to_cross_city_collaboration ?? false),
                'open_to_speaking_at_events' => (bool) ($member->open_to_speaking_at_events ?? false),
                'completeness_percent' => $completenessPercent,
            ],
            'organization' => [
                'country' => (string) ($member->country ?? $member->business_country ?? $primaryCircle?->country ?? ''),
                'state' => (string) ($member->state ?? $member->business_state ?? ''),
                'district_id' => $primaryDistrictId,
                'district_name' => $primaryDistrictName,
                'city' => (string) ($member->city ?? $member->city_of_residence ?? ''),
                'membership_type' => $member->membership_status ? (string) $member->membership_status : ($member->is_sponsored_member ? 'Sponsored Peer' : 'Peer Membership'),
                'membership_status' => (string) ($baseProfile['status'] ?? 'Active'),
                'is_sponsored_member' => (bool) ($member->is_sponsored_member ?? false),
                'joining_date' => $member->created_at ? $member->created_at->format('d M Y') : '',
                'circle_joined_at' => $member->circle_joined_at ? $member->circle_joined_at->format('d M Y') : '',
                'renewal_expiry_date' => $member->membership_ends_at ? $member->membership_ends_at->format('d M Y') : ($activeAppSub['ends_at'] ?? ''),
                'primary_circle' => $primaryCircle ? [
                    'id' => (string) $primaryCircle->id,
                    'name' => (string) $primaryCircle->name,
                    'city' => (string) ($primaryCircle->city ?? ''),
                    'country' => (string) ($primaryCircle->country ?? ''),
                    'stage' => (string) ($primaryCircle->circle_stage ?? ''),
                    'status' => (string) ($primaryCircle->status ?? 'active'),
                ] : null,
                'circles' => $member->circleMembers->map(function ($cm) use ($circlesById, $districtsById): array {
                    $c = $circlesById->get((string) $cm->circle_id);
                    $dId = $c && ! empty($c->district_id) ? (string) $c->district_id : null;

                    return [
                        'circle_id' => (string) ($cm->circle_id ?? ''),
                        'circle_name' => $c ? (string) $c->name : '',
                        'district_name' => $dId ? ($districtsById[$dId] ?? null) : null,
                        'role' => ucwords(str_replace('_', ' ', (string) ($cm->role ?? 'member'))),
                        'status' => ucfirst((string) ($cm->status ?? 'active')),
                        'joined_at' => $cm->joined_at ? $cm->joined_at->format('d M Y') : null,
                        'expires_at' => $cm->expires_at ? $cm->expires_at->format('d M Y') : null,
                    ];
                })->values()->all(),
                'introduced_by' => $introducedByUser ? [
                    'id' => (string) $introducedByUser->id,
                    'name' => $resolveUserLabel($introducedByUser),
                    'company_name' => (string) ($introducedByUser->company_name ?? ''),
                ] : null,
                'assigned_leadership' => $assignedLeadership,
            ],
            'leadership' => [
                'has_leadership_history' => ! empty($currentPositions) || ! empty($historicalPositions) || $reportsSubmittedCount > 0 || ! empty($leadershipCertifications),
                'current_positions' => $currentPositions,
                'historical_positions' => $historicalPositions,
                'reports_submitted_count' => $reportsSubmittedCount,
                'recent_reports' => $recentReports,
                'leadership_certifications' => $leadershipCertifications,
            ],
            'collaborations' => [
                'deals_logged_count' => $dealsGivenCount + $dealsReceivedCount,
                'deals_given_count' => $dealsGivenCount,
                'deals_received_count' => $dealsReceivedCount,
                'deals_given_value_formatted' => $baseProfile['metrics']['deals_given'] ?? '₹0',
                'deals_received_value_formatted' => $baseProfile['metrics']['deals_received'] ?? '₹0',
                'deals_total_value_formatted' => $baseProfile['metrics']['deals_closed'] ?? '₹0',
                'cross_circle_deals_count' => $crossCircleDealsCount,
                'referrals_given_count' => $baseProfile['summary']['referrals_given'] ?? ($baseProfile['metrics']['referrals_given'] ?? 0),
                'referrals_received_count' => $baseProfile['summary']['referrals_received'] ?? ($baseProfile['metrics']['referrals_received'] ?? 0),
                'collaboration_asks_count' => $collabAsksCount,
                'referral_asks_count' => $referralAsksCount,
                'help_asks_count' => $helpAsksCount,
                'requirements_count' => $baseProfile['summary']['requirements'] ?? 0,
                'introduced_peers_count' => $baseProfile['summary']['introduced_peers'] ?? 0,
                'recent_deals' => $recentDeals,
                'recent_asks' => $recentAsks,
            ],
            'life_impact' => [
                'counts' => [
                    'total' => $impactPendingCount + $impactApprovedCount + $impactRejectedCount,
                    'pending' => ($canModerateImpacts || $canManagePeers) ? $impactPendingCount : 0,
                    'approved' => $impactApprovedCount,
                    'rejected' => ($canModerateImpacts || $canManagePeers) ? $impactRejectedCount : 0,
                    'total_lives_impacted' => (int) ($baseProfile['life_impacted_count'] ?? 0),
                ],
                'can_moderate_impacts' => $canModerateImpacts,
                'categories_breakdown' => collect($impactCategoriesMap)
                    ->map(fn ($cnt, $cat) => ['category' => $cat, 'count' => $cnt])
                    ->values()
                    ->all(),
                'submissions' => array_slice($impactSubmissions, 0, 20),
            ],
            'performance' => [
                'circle_attendance' => $circleAttendance,
                'events' => [
                    'registered_count' => $baseProfile['summary']['event_registrations'] ?? 0,
                    'attended_count' => $baseProfile['summary']['event_attendance'] ?? 0,
                    'recent_events' => $eventsResult['data'] ?? [],
                ],
                'p2p_meetings_count' => $baseProfile['summary']['p2p_meetings'] ?? 0,
                'testimonials_given_count' => $baseProfile['summary']['testimonials_given'] ?? 0,
                'testimonials_received_count' => $baseProfile['summary']['testimonials_received'] ?? 0,
                'posts_count' => $baseProfile['summary']['posts'] ?? 0,
                'creatives_count' => $baseProfile['summary']['creatives'] ?? 0,
                'reports_submitted_count' => $reportsSubmittedCount,
                'badges' => $badgesResult['data'] ?? [],
                'coin_milestone_title' => $member->coin_milestone_title ?: null,
                'contribution_award_name' => $member->contribution_award_name ?: null,
            ],
            'compliance' => $complianceData,
            'timeline' => array_slice($timeline, 0, 25),
        ];
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

        $appSubscriptionsCount = Schema::hasTable('user_memberships')
            ? DB::table('user_memberships')->where('user_id', $memberId)->count()
            : 0;

        $circleSubscriptionsCount = Schema::hasTable('circle_subscriptions')
            ? DB::table('circle_subscriptions')->where('user_id', $memberId)->count()
            : 0;

        $introducedPeersCount = DB::table('users')
            ->where(function ($q) use ($memberId): void {
                $q->where('introduced_by', $memberId);

                if (Schema::hasColumn('users', 'referred_by_user_id')) {
                    $q->orWhere('referred_by_user_id', $memberId);
                }

                if (Schema::hasTable('referraldata')) {
                    $q->orWhereIn('id', function ($sub) use ($memberId): void {
                        $sub->select('referred_user_id')
                            ->from('referraldata')
                            ->where('referrer_user_id', $memberId)
                            ->whereNotNull('referred_user_id');
                    });
                }
            })
            ->where('id', '!=', $memberId)
            ->whereNull('deleted_at')
            ->count();

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
            'app_subscriptions' => $appSubscriptionsCount,
            'circle_subscriptions' => $circleSubscriptionsCount,
            'introduced_peers' => $introducedPeersCount,
        ];
    }

    /**
     * Return paginated peers introduced or referred by the member.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberIntroducedPeers(string $memberId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? $filters['limit'] ?? 20)));
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $fromDate = isset($filters['from_date']) ? trim((string) $filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? trim((string) $filters['to_date']) : null;

        $query = User::query()
            ->withoutSystemUser()
            ->where(function ($q) use ($memberId): void {
                $q->where('introduced_by', $memberId);

                if (Schema::hasColumn('users', 'referred_by_user_id')) {
                    $q->orWhere('referred_by_user_id', $memberId);
                }

                if (Schema::hasTable('referraldata')) {
                    $q->orWhereIn('id', function ($sub) use ($memberId): void {
                        $sub->select('referred_user_id')
                            ->from('referraldata')
                            ->where('referrer_user_id', $memberId)
                            ->whereNotNull('referred_user_id');
                    });
                }

                if (Schema::hasTable('peer_recommendations')) {
                    $q->orWhereIn('phone', function ($sub) use ($memberId): void {
                        $sub->select('peer_mobile')
                            ->from('peer_recommendations')
                            ->where('user_id', $memberId)
                            ->whereNotNull('peer_mobile');
                    });
                }
            })
            ->where('id', '!=', $memberId)
            ->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
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

        $query->with([
            'city',
            'profilePhotoFile',
            'coverPhotoFile',
            'introducedBy',
            'level4Category',
            'businessCategory',
            'circleMembers.circle',
            'activeCircle',
        ]);

        $paginator = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (User $user) => $this->formatPeer($user))
            ->filter()
            ->values()
            ->all();

        $memberUser = User::find($memberId);
        $memberName = $memberUser ? trim(($memberUser->first_name ?? '').' '.($memberUser->last_name ?? '')) : null;
        if (empty($memberName) && $memberUser) {
            $memberName = (string) ($memberUser->display_name ?? '');
        }

        $memberData = $memberUser ? [
            'id' => (string) $memberUser->id,
            'name' => $memberName !== '' ? $memberName : null,
            'first_name' => $memberUser->first_name,
            'last_name' => $memberUser->last_name,
            'city' => is_string($memberUser->city) ? $memberUser->city : ($memberUser->city?->name ?? null),
            'business' => $memberUser->company_name ?? $memberUser->business_name,
            'designation' => $memberUser->designation,
            'profile_photo_image' => $memberUser->profile_photo_url,
        ] : null;

        return [
            'data' => [
                'member' => $memberData,
                'introduced_peers_count' => $paginator->total(),
                'introduced_peers' => $items,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Format a peer user into standard peer card structure.
     *
     * @return array<string, mixed>|null
     */
    private function formatPeer(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($fullName === '') {
            $fullName = (string) ($user->display_name ?? 'Peer Member');
        }

        $avatarUrl = $user->profile_photo_url
            ?: ($user->avatar_url
            ?: ($user->avatar ? (str_starts_with((string) $user->avatar, 'http') ? (string) $user->avatar : url('storage/'.$user->avatar)) : null));

        $city = (string) ($user->city ?: ($user->city_of_residence ?: ($user->location ?: 'Ahmedabad')));
        $companyName = (string) ($user->company_name ?: ($user->business_name ?: ($user->company ?: 'Enterprise Services')));

        $circleMemberL4 = null;
        if ($user->relationLoaded('circleMembers') && $user->circleMembers && $user->circleMembers->isNotEmpty()) {
            $circleMemberL4 = $user->circleMembers->first()?->level4Category?->name;
        }

        $level4 = (string) ($circleMemberL4
            ?: ($user->level4Category?->name
            ?: ($user->business_sub_category
            ?: ($user->category_name
            ?: ($user->businessCategory?->name
            ?: ($user->industry ?: 'Business Services'))))));

        $circleName = '';
        $circleId = (string) ($user->active_circle_id ?? '');

        if ($user->relationLoaded('circleMembers') && $user->circleMembers && $user->circleMembers->isNotEmpty()) {
            $c = $user->circleMembers->first()?->circle;
            if ($c) {
                $circleName = (string) $c->name;
                $circleId = (string) $c->id;
            }
        } elseif ($user->relationLoaded('activeCircle') && $user->activeCircle) {
            $circleName = (string) $user->activeCircle->name;
            $circleId = (string) $user->activeCircle->id;
        }

        return [
            'id' => (string) $user->id,
            'user_id' => (string) $user->id,
            'peer_id' => (string) $user->id,
            'name' => $fullName,
            'peer_name' => $fullName,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'display_name' => (string) ($user->display_name ?? $fullName),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? $user->mobile ?? ''),
            'mobile' => (string) ($user->phone ?? $user->mobile ?? ''),
            'profile_image' => $avatarUrl,
            'profile_photo_url' => $avatarUrl,
            'avatar_url' => $avatarUrl,
            'avatar' => $avatarUrl,
            'cover_photo_url' => $user->cover_photo_url ?? null,
            'city' => $city,
            'location' => $city,
            'business_name' => $companyName,
            'company_name' => $companyName,
            'company' => $companyName,
            'business' => $companyName,
            'business_type' => (string) ($user->business_type ?? ''),
            'category_level4' => $level4,
            'level_4_category' => $level4,
            'level4_category' => $level4,
            'category' => $level4,
            'industry' => (string) ($user->industry ?? $user->businessCategory?->name ?? 'Business Services'),
            'designation' => (string) ($user->designation ?? $user->job_title ?? 'Member'),
            'circle_name' => $circleName,
            'circle_id' => $circleId,
            'status' => (string) ($user->status ?? 'active'),
            'membership_status' => (string) ($user->membership_status ?? 'active'),
            'coins_balance' => (int) ($user->coins_balance ?? 0),
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'members_introduced_count' => (int) ($user->members_introduced_count ?? 0),
            'introduced_peers_count' => (int) ($user->members_introduced_count ?? 0),
            'created_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
            'introduced_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
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

        // Batch resolve counterpart and related user objects
        $allUserIds = [];
        foreach ($activities as $act) {
            foreach (['_counterpart_user_id', '_from_user_id', '_to_user_id', '_initiator_user_id', '_peer_user_id', '_substitute_user_id'] as $uKey) {
                if (! empty($act[$uKey])) {
                    $allUserIds[] = (string) $act[$uKey];
                }
            }
        }
        $allUserIds = array_values(array_unique(array_filter($allUserIds)));

        $userMap = [];
        $nameMap = [];
        if (! empty($allUserIds)) {
            $userCols = ['id', 'first_name', 'last_name', 'display_name', 'company_name', 'designation', 'phone', 'email', 'profile_photo_url', 'city'];
            $existingCols = array_values(array_filter($userCols, fn ($c) => Schema::hasColumn('users', $c)));
            $users = DB::table('users')
                ->whereIn('id', $allUserIds)
                ->whereRaw("LOWER(COALESCE(email, '')) != 'info@peersglobal.com'")
                ->whereRaw("LOWER(COALESCE(display_name, '')) NOT LIKE '%genie%'")
                ->select($existingCols)
                ->get();

            foreach ($users as $u) {
                $displayName = trim((string) ($u->display_name ?? ''));
                $fullName = trim(trim((string) ($u->first_name ?? '')).' '.trim((string) ($u->last_name ?? '')));
                $resolvedName = $displayName !== '' ? $displayName : ($fullName !== '' ? $fullName : (trim((string) ($u->first_name ?? '')) ?: 'Member'));

                $nameMap[$u->id] = $resolvedName;

                $userMap[$u->id] = [
                    'id' => (string) $u->id,
                    'name' => $resolvedName,
                    'first_name' => (string) ($u->first_name ?? ''),
                    'last_name' => (string) ($u->last_name ?? ''),
                    'display_name' => (string) ($u->display_name ?? ''),
                    'company_name' => (string) ($u->company_name ?? ''),
                    'designation' => (string) ($u->designation ?? ''),
                    'phone' => (string) ($u->phone ?? ''),
                    'email' => (string) ($u->email ?? ''),
                    'profile_photo_url' => (string) ($u->profile_photo_url ?? ''),
                    'city' => (string) ($u->city ?? ''),
                ];
            }
        }

        // Attach resolved objects and clean internal keys
        foreach ($activities as &$item) {
            // Counterpart
            if (array_key_exists('_counterpart_user_id', $item)) {
                $cid = $item['_counterpart_user_id'];
                $item['counterpart_name'] = $cid ? ($nameMap[$cid] ?? 'Member') : null;
                $item['counterpart'] = $cid ? ($userMap[$cid] ?? null) : null;
                if (isset($item['data']) && is_array($item['data'])) {
                    $item['data']['counterpart'] = $item['counterpart'];
                }
                unset($item['_counterpart_user_id']);
            }
            if (! array_key_exists('counterpart_name', $item)) {
                $item['counterpart_name'] = null;
            }
            if (! array_key_exists('counterpart', $item)) {
                $item['counterpart'] = null;
            }

            // From / To Users (Deals, Referrals, Testimonials)
            if (array_key_exists('_from_user_id', $item)) {
                $fid = $item['_from_user_id'];
                $item['from_user'] = $fid ? ($userMap[$fid] ?? null) : null;
                if (isset($item['data']) && is_array($item['data'])) {
                    $item['data']['from_user'] = $item['from_user'];
                }
                unset($item['_from_user_id']);
            }
            if (array_key_exists('_to_user_id', $item)) {
                $tid = $item['_to_user_id'];
                $item['to_user'] = $tid ? ($userMap[$tid] ?? null) : null;
                if (isset($item['data']) && is_array($item['data'])) {
                    $item['data']['to_user'] = $item['to_user'];
                }
                unset($item['_to_user_id']);
            }

            // Initiator / Peer Users (P2P Meetings)
            if (array_key_exists('_initiator_user_id', $item)) {
                $iid = $item['_initiator_user_id'];
                $item['initiator_user'] = $iid ? ($userMap[$iid] ?? null) : null;
                if (isset($item['data']) && is_array($item['data'])) {
                    $item['data']['initiator_user'] = $item['initiator_user'];
                }
                unset($item['_initiator_user_id']);
            }
            if (array_key_exists('_peer_user_id', $item)) {
                $pid = $item['_peer_user_id'];
                $item['peer_user'] = $pid ? ($userMap[$pid] ?? null) : null;
                if (isset($item['data']) && is_array($item['data'])) {
                    $item['data']['peer_user'] = $item['peer_user'];
                }
                unset($item['_peer_user_id']);
            }

            // Substitute User (Attendance)
            if (array_key_exists('_substitute_user_id', $item)) {
                $sid = $item['_substitute_user_id'];
                $item['substitute_user'] = $sid ? ($userMap[$sid] ?? null) : null;
                if (isset($item['data']) && is_array($item['data'])) {
                    $item['data']['substitute_user'] = $item['substitute_user'];
                }
                unset($item['_substitute_user_id']);
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

                $media = null;
                if (! empty($row->media)) {
                    $media = is_string($row->media) ? (json_decode($row->media, true) ?? $row->media) : $row->media;
                }

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'p2p_meeting',
                    'title' => $title,
                    'counterpart_name' => null,
                    'counterpart' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    '_initiator_user_id' => (string) ($row->initiator_user_id ?? ''),
                    '_peer_user_id' => (string) ($row->peer_user_id ?? ''),
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'meeting_date' => $row->meeting_date ? (string) $row->meeting_date : null,
                    'meeting_place' => (string) ($row->meeting_place ?? ''),
                    'remarks' => (string) ($row->remarks ?? ''),
                    'media' => $media,
                    'member_role' => $isInitiator ? 'initiator' : 'peer',
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
                    'data' => [
                        'meeting_id' => (string) $row->id,
                        'meeting_date' => $row->meeting_date ? (string) $row->meeting_date : null,
                        'meeting_place' => (string) ($row->meeting_place ?? ''),
                        'remarks' => (string) ($row->remarks ?? ''),
                        'media' => $media,
                        'member_role' => $isInitiator ? 'initiator' : 'peer',
                        'status' => $status,
                        'initiator_user_id' => (string) ($row->initiator_user_id ?? ''),
                        'peer_user_id' => (string) ($row->peer_user_id ?? ''),
                        'initiator_user' => null,
                        'peer_user' => null,
                        'counterpart' => null,
                        'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                        'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
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
                    'counterpart' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    '_from_user_id' => (string) ($row->from_user_id ?? ''),
                    '_to_user_id' => (string) ($row->to_user_id ?? ''),
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'referral_of' => (string) ($row->referral_of ?? ''),
                    'referral_type' => (string) ($row->referral_type ?? 'b2b_referral'),
                    'referral_date' => $row->referral_date ? (string) $row->referral_date : null,
                    'phone' => (string) ($row->phone ?? ''),
                    'email' => (string) ($row->email ?? ''),
                    'address' => (string) ($row->address ?? ''),
                    'hot_value' => $row->hot_value ?? null,
                    'remarks' => (string) ($row->remarks ?? ''),
                    'status_id' => $row->status_id ? (int) $row->status_id : null,
                    'status_name' => (string) ($row->status_name ?? $status),
                    'member_role' => $isGiven ? 'giver' : 'receiver',
                    'date' => $isoDate,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
                    'data' => [
                        'referral_id' => (string) $row->id,
                        'referral_of' => (string) ($row->referral_of ?? ''),
                        'referral_type' => (string) ($row->referral_type ?? 'b2b_referral'),
                        'referral_date' => $row->referral_date ? (string) $row->referral_date : null,
                        'phone' => (string) ($row->phone ?? ''),
                        'email' => (string) ($row->email ?? ''),
                        'address' => (string) ($row->address ?? ''),
                        'hot_value' => $row->hot_value ?? null,
                        'remarks' => (string) ($row->remarks ?? ''),
                        'status' => $status,
                        'status_id' => $row->status_id ? (int) $row->status_id : null,
                        'status_name' => (string) ($row->status_name ?? $status),
                        'member_role' => $isGiven ? 'giver' : 'receiver',
                        'from_user_id' => (string) ($row->from_user_id ?? ''),
                        'to_user_id' => (string) ($row->to_user_id ?? ''),
                        'from_user' => null,
                        'to_user' => null,
                        'counterpart' => null,
                        'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                        'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
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
                    : ($isGiver ? 'Business Deal Given' : 'Business Deal Received');

                $description = (string) ($row->comment ?: 'Closed business transaction.');

                return [
                    'id' => (string) $row->id,
                    'activity_type' => $activityType,
                    'title' => $title,
                    'counterpart_name' => null,
                    'counterpart' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    '_from_user_id' => (string) ($row->from_user_id ?? ''),
                    '_to_user_id' => (string) ($row->to_user_id ?? ''),
                    'description' => $description,
                    'amount' => $formattedAmount,
                    'deal_amount' => $dealAmount,
                    'business_type' => (string) ($row->business_type ?? ''),
                    'deal_date' => $row->deal_date ? (string) $row->deal_date : null,
                    'comment' => (string) ($row->comment ?? ''),
                    'referral_id' => $row->referral_id ? (string) $row->referral_id : null,
                    'member_role' => $isGiver ? 'giver' : 'receiver',
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
                    'data' => [
                        'deal_id' => (string) $row->id,
                        'deal_amount' => $dealAmount,
                        'deal_amount_formatted' => $formattedAmount,
                        'business_type' => (string) ($row->business_type ?? ''),
                        'deal_date' => $row->deal_date ? (string) $row->deal_date : null,
                        'comment' => (string) ($row->comment ?? ''),
                        'referral_id' => $row->referral_id ? (string) $row->referral_id : null,
                        'member_role' => $isGiver ? 'giver' : 'receiver',
                        'status' => $status,
                        'from_user_id' => (string) ($row->from_user_id ?? ''),
                        'to_user_id' => (string) ($row->to_user_id ?? ''),
                        'from_user' => null,
                        'to_user' => null,
                        'counterpart' => null,
                        'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                        'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
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
                        'ar.meeting_id',
                        'ar.circle_id',
                        'ar.status',
                        'ar.marked_at',
                        'ar.notes',
                        'ar.substitute_user_id',
                        'ar.created_at',
                        'cm.meeting_date',
                        'cm.meeting_number',
                        'cm.venue',
                        'cm.mode',
                        'cm.meeting_link',
                        'cm.agenda',
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
                    'attendance_type' => 'circle_meeting',
                    'title' => $title,
                    'counterpart_name' => null,
                    'counterpart' => null,
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'meeting_date' => $row->meeting_date ? (string) $row->meeting_date : null,
                    'venue' => (string) ($row->venue ?? ''),
                    'mode' => (string) ($row->mode ?? ''),
                    'meeting_link' => (string) ($row->meeting_link ?? ''),
                    'circle_id' => (string) ($row->circle_id ?? ''),
                    'circle_name' => $circleName,
                    'notes' => (string) ($row->notes ?? ''),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : $isoDate,
                    '_substitute_user_id' => ! empty($row->substitute_user_id) ? (string) $row->substitute_user_id : null,
                    'data' => [
                        'record_id' => (string) $row->id,
                        'attendance_type' => 'circle_meeting',
                        'status' => $status,
                        'marked_at' => $row->marked_at ? (string) $row->marked_at : null,
                        'meeting_date' => $row->meeting_date ? (string) $row->meeting_date : null,
                        'meeting_id' => (string) ($row->meeting_id ?? ''),
                        'meeting_number' => $row->meeting_number ?? null,
                        'circle_id' => (string) ($row->circle_id ?? ''),
                        'circle_name' => $circleName,
                        'venue' => (string) ($row->venue ?? ''),
                        'mode' => (string) ($row->mode ?? ''),
                        'meeting_link' => (string) ($row->meeting_link ?? ''),
                        'agenda' => (string) ($row->agenda ?? ''),
                        'notes' => (string) ($row->notes ?? ''),
                        'substitute_user_id' => ! empty($row->substitute_user_id) ? (string) $row->substitute_user_id : null,
                        'substitute_user' => null,
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
                    'e.banner_url',
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
                    'attendance_type' => 'event',
                    'title' => $title,
                    'counterpart_name' => null,
                    'counterpart' => null,
                    'description' => $description,
                    'amount' => null,
                    'status' => $status,
                    'date' => $isoDate,
                    'event_id' => (string) ($erRow->event_id ?? ''),
                    'event_title' => (string) ($erRow->event_title ?? ''),
                    'event_start_at' => $erRow->event_start_at ? (string) $erRow->event_start_at : null,
                    'checkin_status' => (string) ($erRow->checkin_status ?? ''),
                    'created_at' => $erRow->created_at ? Carbon::parse($erRow->created_at)->toIso8601String() : $isoDate,
                    'data' => [
                        'registration_id' => (string) $erRow->id,
                        'attendance_type' => 'event',
                        'event_id' => (string) ($erRow->event_id ?? ''),
                        'event_title' => (string) ($erRow->event_title ?? ''),
                        'event_start_at' => $erRow->event_start_at ? (string) $erRow->event_start_at : null,
                        'location_text' => (string) ($erRow->location_text ?? ''),
                        'is_virtual' => (bool) ($erRow->is_virtual ?? false),
                        'banner_url' => (string) ($erRow->banner_url ?? ''),
                        'checkin_status' => (string) ($erRow->checkin_status ?? ''),
                        'checked_in_at' => $erRow->checked_in_at ? (string) $erRow->checked_in_at : null,
                        'registration_status' => (string) ($erRow->registration_status ?? ''),
                        'status' => $status,
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

                $metadata = null;
                if (! empty($row->metadata)) {
                    $metadata = is_string($row->metadata) ? (json_decode($row->metadata, true) ?? $row->metadata) : $row->metadata;
                }

                return [
                    'id' => $id,
                    'activity_type' => 'coins',
                    'title' => $title,
                    'counterpart_name' => null,
                    'counterpart' => null,
                    'description' => $description,
                    'amount' => $amountStr,
                    'coins_delta' => $coinsDelta,
                    'balance_after' => $row->balance_after ?? null,
                    'entry_type' => (string) ($row->entry_type ?? ($coinsDelta >= 0 ? 'CREDIT' : 'DEBIT')),
                    'bucket' => (string) ($row->bucket ?? 'EARNED'),
                    'reference' => (string) ($row->reference ?? ''),
                    'reference_type' => (string) ($row->reference_type ?? ''),
                    'reference_id' => (string) ($row->reference_id ?? ''),
                    'remark' => (string) ($row->remark ?? ''),
                    'status' => $status,
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => [
                        'transaction_id' => $id,
                        'coins_delta' => $coinsDelta,
                        'amount' => (float) ($row->amount ?? $coinsDelta),
                        'balance_after' => $row->balance_after ?? null,
                        'entry_type' => (string) ($row->entry_type ?? ($coinsDelta >= 0 ? 'CREDIT' : 'DEBIT')),
                        'bucket' => (string) ($row->bucket ?? 'EARNED'),
                        'reference' => (string) ($row->reference ?? ''),
                        'reference_type' => (string) ($row->reference_type ?? ''),
                        'reference_id' => (string) ($row->reference_id ?? ''),
                        'remark' => (string) ($row->remark ?? ''),
                        'metadata' => $metadata,
                        'status' => $status,
                        'created_at' => $isoDate,
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

        $q = Impact::query()
            ->with(['user', 'impactedPeer.circleMembers.circle', 'approvedBy', 'approvedByUser', 'rejectedBy', 'rejectedByUser'])
            ->where('user_id', $memberId);

        if (Schema::hasColumn('impacts', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        $this->applyDateFilters($q, 'created_at', $fromDate, $toDate);

        if ($search) {
            $q->where(function ($sq) use ($search): void {
                $sq->where('action', 'like', "%{$search}%")
                    ->orWhere('story_to_share', 'like', "%{$search}%");
            });
        }

        $activitiesService = app(LeaderActivitiesService::class);

        return $q->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(function (Impact $impact) use ($activitiesService): array {
                $formatted = $activitiesService->formatImpactRecord($impact, null, includeHistory: false);
                $createdDate = $impact->created_at ? Carbon::parse($impact->created_at) : now();
                $isoDate = $createdDate->toIso8601String();
                $ts = $createdDate->timestamp;

                return [
                    'id' => (string) $impact->id,
                    'activity_type' => 'life_impact',
                    'title' => $formatted['title'] ?: 'Life Impact',
                    'counterpart_name' => $formatted['beneficiary']['name'] ?? null,
                    'counterpart' => $formatted['beneficiary'] ?? null,
                    'description' => $formatted['description'] ?: ($formatted['action'] ?: ''),
                    'amount' => null,
                    'status' => $formatted['status_label'] ?? 'Approved',
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'data' => $formatted,
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

                $media = null;
                if (! empty($row->media)) {
                    $media = is_string($row->media) ? (json_decode($row->media, true) ?? $row->media) : $row->media;
                }

                return [
                    'id' => (string) $row->id,
                    'activity_type' => 'testimonial',
                    'title' => $isAuthor ? 'Testimonial Given' : 'Testimonial Received',
                    'counterpart_name' => null,
                    'counterpart' => null,
                    '_counterpart_user_id' => $counterpartUserId,
                    '_from_user_id' => (string) ($row->from_user_id ?? ''),
                    '_to_user_id' => (string) ($row->to_user_id ?? ''),
                    'description' => (string) mb_substr((string) ($row->content ?? ''), 0, 120),
                    'content' => (string) ($row->content ?? ''),
                    'rating' => $row->rating ? (int) $row->rating : null,
                    'media' => $media,
                    'referral_id' => $row->referral_id ? (string) $row->referral_id : null,
                    'member_role' => $isAuthor ? 'author' : 'recipient',
                    'amount' => null,
                    'status' => 'Completed',
                    'date' => $isoDate,
                    'created_at' => $isoDate,
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
                    'data' => [
                        'testimonial_id' => (string) $row->id,
                        'content' => (string) ($row->content ?? ''),
                        'rating' => $row->rating ? (int) $row->rating : null,
                        'media' => $media,
                        'referral_id' => $row->referral_id ? (string) $row->referral_id : null,
                        'member_role' => $isAuthor ? 'author' : 'recipient',
                        'status' => 'Completed',
                        'from_user_id' => (string) ($row->from_user_id ?? ''),
                        'to_user_id' => (string) ($row->to_user_id ?? ''),
                        'from_user' => null,
                        'to_user' => null,
                        'counterpart' => null,
                        'created_at' => $isoDate,
                        'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
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
            ->withCount(['comments', 'likes'])
            ->with([
                'likes' => fn ($q) => $q->orderByDesc('created_at')->with([
                    'user.circleMembers.circle',
                    'user.activeCircle',
                ]),
                'comments' => fn ($q) => $q->orderByDesc('created_at')->with([
                    'user.circleMembers.circle',
                    'user.activeCircle',
                ]),
            ]);

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
            $likesList = $post->relationLoaded('likes')
                ? $post->likes->map(function ($like): array {
                    $user = $like->user;
                    $peer = $user ? $this->formatPeer($user) : null;
                    $likedAt = $like->created_at ? Carbon::parse($like->created_at)->toIso8601String() : null;

                    return [
                        'id' => (string) $like->id,
                        'like_id' => (string) $like->id,
                        'post_id' => (string) $like->post_id,
                        'user_id' => (string) ($user?->id ?? $like->user_id),
                        'peer_id' => (string) ($user?->id ?? $like->user_id),
                        'name' => $peer['name'] ?? 'Verified Peer',
                        'peer_name' => $peer['name'] ?? 'Verified Peer',
                        'first_name' => $peer['first_name'] ?? '',
                        'last_name' => $peer['last_name'] ?? '',
                        'profile_photo_url' => $peer['profile_photo_url'] ?? null,
                        'avatar_url' => $peer['avatar_url'] ?? null,
                        'avatar' => $peer['avatar'] ?? null,
                        'profile_image' => $peer['profile_image'] ?? null,
                        'company_name' => $peer['company_name'] ?? null,
                        'company' => $peer['company'] ?? null,
                        'business_name' => $peer['business_name'] ?? null,
                        'designation' => $peer['designation'] ?? null,
                        'city' => $peer['city'] ?? null,
                        'location' => $peer['location'] ?? null,
                        'circle_name' => $peer['circle_name'] ?? null,
                        'circle_id' => $peer['circle_id'] ?? null,
                        'level4_category' => $peer['level4_category'] ?? null,
                        'category_level4' => $peer['category_level4'] ?? null,
                        'liked_at' => $likedAt,
                        'created_at' => $likedAt,
                        'user' => $peer,
                        'peer' => $peer,
                    ];
                })->values()->all()
                : [];

            $likedPeers = $post->relationLoaded('likes')
                ? $post->likes->map(fn ($like) => $like->user ? $this->formatPeer($like->user) : null)
                    ->filter()
                    ->values()
                    ->all()
                : [];

            $commentsList = $post->relationLoaded('comments')
                ? $post->comments->map(function ($comment): array {
                    $user = $comment->user;
                    $peer = $user ? $this->formatPeer($user) : null;
                    $commentCreatedAt = $comment->created_at ? Carbon::parse($comment->created_at)->toIso8601String() : null;

                    return [
                        'id' => (string) $comment->id,
                        'comment_id' => (string) $comment->id,
                        'post_id' => (string) $comment->post_id,
                        'user_id' => (string) ($user?->id ?? $comment->user_id),
                        'peer_id' => (string) ($user?->id ?? $comment->user_id),
                        'content' => (string) ($comment->content ?? ''),
                        'comment' => (string) ($comment->content ?? ''),
                        'body' => (string) ($comment->content ?? ''),
                        'name' => $peer['name'] ?? 'Peer Member',
                        'peer_name' => $peer['name'] ?? 'Peer Member',
                        'author_id' => (string) ($user?->id ?? $comment->user_id),
                        'author_name' => $peer['name'] ?? 'Peer Member',
                        'author_avatar' => $peer['profile_photo_url'] ?? null,
                        'first_name' => $peer['first_name'] ?? '',
                        'last_name' => $peer['last_name'] ?? '',
                        'profile_photo_url' => $peer['profile_photo_url'] ?? null,
                        'avatar_url' => $peer['avatar_url'] ?? null,
                        'avatar' => $peer['avatar'] ?? null,
                        'profile_image' => $peer['profile_image'] ?? null,
                        'company_name' => $peer['company_name'] ?? null,
                        'company' => $peer['company'] ?? null,
                        'business_name' => $peer['business_name'] ?? null,
                        'designation' => $peer['designation'] ?? null,
                        'city' => $peer['city'] ?? null,
                        'location' => $peer['location'] ?? null,
                        'circle_name' => $peer['circle_name'] ?? null,
                        'circle_id' => $peer['circle_id'] ?? null,
                        'level4_category' => $peer['level4_category'] ?? null,
                        'category_level4' => $peer['category_level4'] ?? null,
                        'created_at' => $commentCreatedAt,
                        'user' => $peer,
                        'peer' => $peer,
                    ];
                })->values()->all()
                : [];

            $commentPeers = $post->relationLoaded('comments')
                ? $post->comments->map(fn ($comment) => $comment->user ? $this->formatPeer($comment->user) : null)
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->all()
                : [];

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
                'likes' => $likesList,
                'liked_peers' => $likedPeers,
                'likes_list' => $likesList,
                'comments' => $commentsList,
                'comment_peers' => $commentPeers,
                'comments_list' => $commentsList,
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

    /**
     * Delete a member's post.
     *
     * @return array{success: bool, message: string, error_code?: string, status_code: int}
     */
    public function deleteMemberPost(string $memberId, string $postId, User $leader): array
    {
        $member = $this->resolveMember($memberId, $leader);

        if (! $member) {
            return [
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'status_code' => 404,
            ];
        }

        $post = Post::query()
            ->where('id', $postId)
            ->where('user_id', $memberId)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return [
                'success' => false,
                'message' => 'Post not found or already deleted.',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'status_code' => 404,
            ];
        }

        DB::transaction(function () use ($post): void {
            $post->is_deleted = true;
            $post->active = false;
            $post->is_active = false;
            $post->status = 'inactive';
            $post->save();
            $post->delete();
        });

        return [
            'success' => true,
            'message' => 'User post deleted successfully.',
            'status_code' => 200,
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

    /**
     * Delete a member's creative.
     *
     * @return array{success: bool, message: string, error_code?: string, status_code: int}
     */
    public function deleteMemberCreative(string $memberId, string $creativeId, User $leader): array
    {
        $member = $this->resolveMember($memberId, $leader);

        if (! $member) {
            return [
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'status_code' => 404,
            ];
        }

        $creative = ActivityCreative::query()
            ->where('id', $creativeId)
            ->where('user_id', $memberId)
            ->whereNull('deleted_at')
            ->first();

        if (! $creative) {
            return [
                'success' => false,
                'message' => 'Creative not found or already deleted.',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'status_code' => 404,
            ];
        }

        DB::transaction(function () use ($creative): void {
            $creative->delete();
        });

        return [
            'success' => true,
            'message' => 'Member creative deleted successfully.',
            'status_code' => 200,
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
            ->whereNull('deleted_at')
            ->when(Schema::hasColumn('events', 'is_active'), fn ($q) => $q->where('is_active', true));

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
