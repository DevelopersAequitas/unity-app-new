<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Policies\SponsorshipMilestonePolicy;
use App\Services\Sponsorship\SponsorshipMilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class SponsoredMembersMilestonesController extends BaseApiController
{
    public function __construct(
        private readonly SponsorshipMilestoneService $milestoneService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-sponsored-milestones', SponsorshipMilestonePolicy::class);

        $perPage = $request->integer('per_page', 10);
        $search = trim((string) $request->query('search', ''));
        $memberId = $request->query('member_id');
        $milestoneFilter = $request->query('milestone');
        $awardNameFilter = $request->query('award_name');

        $sortBy = $request->query('sort_by', 'total_sponsored_members');
        $sortOrder = $request->query('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        // 1. Build subquery for total sponsored count
        $subquery = User::query()
            ->from('users as sponsored')
            ->selectRaw('count(*)')
            ->whereColumn('sponsored.introduced_by', 'users.id')
            ->where('sponsored.is_sponsored_member', true)
            ->whereNull('sponsored.deleted_at');

        if (Schema::hasColumn('users', 'status')) {
            $subquery->whereNotIn(DB::raw('CAST(sponsored.status AS TEXT)'), ['rejected', 'cancelled', 'inactive', 'pending']);
        }
        if (Schema::hasColumn('users', 'approval_status')) {
            $subquery->where('sponsored.approval_status', 'approved');
        }

        // 2. Main query
        $query = User::query()
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.display_name', 'users.email', 'users.phone', 'users.company_name', 'users.profile_photo_url')
            ->selectSub($subquery, 'total_sponsored_members');

        // Search filter
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('display_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like)
                    ->orWhere('phone', 'ILIKE', $like)
                    ->orWhere('company_name', 'ILIKE', $like);
            });
        }

        // Member ID filter
        if ($memberId) {
            $query->where('users.id', $memberId);
        }

        // Target milestone ranges
        $targetMilestone = null;
        if ($milestoneFilter !== null && $milestoneFilter !== '') {
            $targetMilestone = (int) $milestoneFilter;
        } elseif ($awardNameFilter !== null && $awardNameFilter !== '') {
            $targetMilestone = SponsorshipMilestoneService::getMilestoneForAwardName($awardNameFilter);
        }

        if ($targetMilestone !== null) {
            $range = SponsorshipMilestoneService::getCountRangeForMilestone($targetMilestone);
            if ($range) {
                [$min, $max] = $range;
                $query->whereSub($subquery, '>=', $min);
                if ($max !== null) {
                    $query->whereSub($subquery, '<=', $max);
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Sorting
        if ($sortBy === 'total_sponsored_members') {
            $query->orderBy('total_sponsored_members', $sortOrder)
                ->orderBy('users.display_name', 'asc');
        } else {
            $query->orderBy('users.display_name', $sortOrder);
        }

        $paginated = $query->paginate($perPage);

        // Load all sponsored members for this page's sponsors in one query (avoid N+1)
        $sponsorIds = collect($paginated->items())->pluck('id')->all();

        $sponsoredByMap = collect();
        if (! empty($sponsorIds)) {
            // Mirror the same conditions as SponsorshipMilestoneService::buildSponsoredMembersQuery
            // but use whereIn instead of a single where to load all sponsors at once.
            $sponsoredQuery = User::query()
                ->whereIn('introduced_by', $sponsorIds)
                ->where('is_sponsored_member', true)
                ->select([
                    'id',
                    'introduced_by',
                    'display_name',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'company_name',
                    'designation',
                    'city',
                    'profile_photo_url',
                    'membership_status',
                    'created_at',
                    'status',
                ]);

            if (Schema::hasColumn('users', 'status')) {
                $sponsoredQuery->whereNotIn(DB::raw('CAST(status AS TEXT)'), ['rejected', 'cancelled', 'inactive', 'pending']);
            }

            if (Schema::hasColumn('users', 'approval_status')) {
                $sponsoredQuery->where('approval_status', 'approved');
            }

            $sponsoredByMap = $sponsoredQuery
                ->orderBy('display_name', 'asc')
                ->get()
                ->groupBy('introduced_by');
        }

        $data = collect($paginated->items())->map(function (User $user) use ($sponsoredByMap): array {
            $count = (int) $user->total_sponsored_members;
            $milestoneDetails = $this->milestoneService->resolveMilestone($count);

            $sponsoredList = ($sponsoredByMap->get($user->id) ?? collect())
                ->map(fn (User $s): array => [
                    'id' => $s->id,
                    'display_name' => $s->display_name ?: trim(($s->first_name ?? '').' '.($s->last_name ?? '')),
                    'first_name' => $s->first_name,
                    'last_name' => $s->last_name,
                    'email' => $s->email,
                    'phone' => $s->phone,
                    'company_name' => $s->company_name,
                    'designation' => $s->designation,
                    'city' => $s->city,
                    'profile_photo_url' => $s->profile_photo_url,
                    'membership_status' => $s->membership_status,
                    'sponsored_at' => $s->created_at ? $s->created_at->toIso8601String() : null,
                    'status' => $s->status,
                ])
                ->values()
                ->all();

            return [
                'member_id' => $user->id,
                'member_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'phone' => $user->phone,
                'company_name' => $user->company_name,
                'profile_photo_url' => $user->profile_photo_url,
                'total_sponsored_members' => $count,
                'current_milestone' => $milestoneDetails['current_milestone'],
                'award_name' => $milestoneDetails['award_name'],
                'recognition' => $milestoneDetails['recognition'],
                'next_milestone' => $milestoneDetails['next_milestone'],
                'members_remaining' => $milestoneDetails['members_remaining'],
                'sponsored_members' => $sponsoredList,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Member sponsorship milestones fetched successfully.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('view-member-sponsored-milestones', SponsorshipMilestonePolicy::class);

        $member = User::findOrFail($id);

        // Calculate count of sponsored members
        $sponsoredCount = $this->milestoneService->countSponsoredMembers($member->id);
        $milestoneDetails = $this->milestoneService->resolveMilestone($sponsoredCount);

        // Fetch paginated sponsored members list
        $perPage = $request->integer('per_page', 20);
        $sponsoredQuery = $this->milestoneService->buildSponsoredMembersQuery($member->id)
            ->orderBy('display_name', 'asc');

        $paginated = $sponsoredQuery->paginate($perPage);

        $sponsoredMembers = collect($paginated->items())->map(function (User $user): array {
            return [
                'id' => $user->id,
                'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'company_name' => $user->company_name,
                'city' => $user->city,
                'profile_photo_url' => $user->profile_photo_url,
                'sponsored_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
                'status' => $user->status,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Member sponsorship milestone details fetched successfully.',
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'name' => $member->display_name ?: trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'company_name' => $member->company_name,
                    'profile_photo_url' => $member->profile_photo_url,
                ],
                'milestone_summary' => [
                    'total_sponsored_members' => $sponsoredCount,
                    'current_milestone' => $milestoneDetails['current_milestone'],
                    'award_name' => $milestoneDetails['award_name'],
                    'recognition' => $milestoneDetails['recognition'],
                    'next_milestone' => $milestoneDetails['next_milestone'],
                    'members_remaining' => $milestoneDetails['members_remaining'],
                ],
                'sponsored_members' => $sponsoredMembers,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
        ]);
    }
}
