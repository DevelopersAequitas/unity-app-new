<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Policies\SponsorshipMilestonePolicy;
use App\Services\Sponsorship\SponsorshipMilestoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SponsoredMembersMilestonesWebController extends Controller
{
    public function __construct(
        private readonly SponsorshipMilestoneService $milestoneService
    ) {}

    public function index(Request $request): View
    {
        Gate::forUser(auth('admin')->user())->authorize('view-sponsored-milestones', SponsorshipMilestonePolicy::class);

        $perPage = $request->integer('per_page', 20);
        if (! in_array($perPage, [10, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }

        $search = trim((string) $request->query('q', ''));
        $milestoneFilter = $request->query('milestone');
        $awardFilter = $request->query('award_name');

        $sortBy = $request->query('sort', 'total_sponsored_members');
        $sortOrder = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

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
            ->select('users.*')
            ->selectSub($subquery, 'total_sponsored_members');

        // Apply filters
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('display_name', 'ILIKE', $like)
                    ->orWhere('first_name', 'ILIKE', $like)
                    ->orWhere('last_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like)
                    ->orWhere('phone', 'ILIKE', $like)
                    ->orWhere('company_name', 'ILIKE', $like);
            });
        }

        $targetMilestone = null;
        if ($milestoneFilter !== null && $milestoneFilter !== '') {
            $targetMilestone = (int) $milestoneFilter;
        } elseif ($awardFilter !== null && $awardFilter !== '') {
            $targetMilestone = SponsorshipMilestoneService::getMilestoneForAwardName($awardFilter);
        }

        if ($targetMilestone !== null) {
            $range = SponsorshipMilestoneService::getCountRangeForMilestone($targetMilestone);
            if ($range) {
                [$min, $max] = $range;
                $query->where($subquery, '>=', $min);
                if ($max !== null) {
                    $query->where($subquery, '<=', $max);
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

        $paginated = $query->paginate($perPage)->withQueryString();

        // Attach milestone info to paginated items
        $paginated->getCollection()->transform(function (User $user) {
            $count = (int) $user->total_sponsored_members;
            $milestoneDetails = $this->milestoneService->resolveMilestone($count);
            $user->milestoneDetails = $milestoneDetails;

            return $user;
        });

        $filters = [
            'search' => $search,
            'milestone' => $milestoneFilter,
            'award_name' => $awardFilter,
            'per_page' => $perPage,
            'sort' => $sortBy,
            'dir' => $request->query('dir', 'desc'),
        ];

        return view('admin.sponsored-milestones.index', [
            'members' => $paginated,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        Gate::forUser(auth('admin')->user())->authorize('view-member-sponsored-milestones', SponsorshipMilestonePolicy::class);

        $sponsoredCount = $this->milestoneService->countSponsoredMembers($user->id);
        $milestoneDetails = $this->milestoneService->resolveMilestone($sponsoredCount);

        $perPage = $request->integer('per_page', 20);
        if (! in_array($perPage, [10, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }

        $sponsoredQuery = $this->milestoneService->buildSponsoredMembersQuery($user->id)
            ->orderBy('display_name', 'asc');

        $paginated = $sponsoredQuery->paginate($perPage)->withQueryString();

        return view('admin.sponsored-milestones.show', [
            'member' => $user,
            'sponsoredCount' => $sponsoredCount,
            'milestoneDetails' => $milestoneDetails,
            'sponsoredMembers' => $paginated,
        ]);
    }
}
