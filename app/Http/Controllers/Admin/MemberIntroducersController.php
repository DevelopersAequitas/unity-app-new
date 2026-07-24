<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IndustryDirector\IndustryScopeService;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberIntroducersController extends Controller
{
    /**
     * Display the listing of member introducers.
     */
    public function index(Request $request): View
    {
        $adminUser = Auth::guard('admin')->user();
        if (! $adminUser) {
            abort(401);
        }

        $canEditUsers = AdminAccess::canEditUsers($adminUser);

        // Section A: Top 10 Query (ordered by count desc, then alphabetically by name asc)
        $topIntroducersQuery = User::query()
            ->withCount(['introducedMembers'])
            ->with(['city', 'introducedBy'])
            ->has('introducedMembers')
            ->orderByDesc('introduced_members_count')
            ->orderBy('display_name', 'asc')
            ->limit(10);

        $this->applyScopes($topIntroducersQuery, $adminUser);
        $topIntroducers = $topIntroducersQuery->get();

        // Section B: All Introducers Query
        $query = User::query()
            ->with(['city', 'introducedBy']);

        $this->applyScopes($query, $adminUser);

        // Filters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = trim((string) $request->input('q', ''));
        $membershipStatus = $request->input('membership_status');
        $perPage = $request->integer('per_page') ?: 20;
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        // Apply Date Range Filter on introduced date (created_at of the introduced member)
        if ($startDate || $endDate) {
            $query->whereHas('introducedMembers', function (Builder $q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                }
            });

            $query->withCount(['introducedMembers' => function (Builder $q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                }
            }]);
        } else {
            $query->whereHas('introducedMembers');
            $query->withCount(['introducedMembers']);
        }

        // Apply Search
        if ($search !== '') {
            if (Str::isUuid($search)) {
                $query->where('users.id', $search);
            } else {
                $words = array_filter(explode(' ', $search));
                $query->where(function (Builder $q) use ($words) {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $q->where(function (Builder $sub) use ($like) {
                            $searchableColumns = [
                                'name',
                                'display_name',
                                'first_name',
                                'last_name',
                                'email',
                                'company',
                                'company_name',
                                'business_name',
                                'city',
                                'phone',
                                'designation',
                            ];

                            $hasSearchColumn = false;
                            foreach ($searchableColumns as $column) {
                                if (! Schema::hasColumn('users', $column)) {
                                    continue;
                                }
                                if (! $hasSearchColumn) {
                                    $sub->where($column, 'ILIKE', $like);
                                    $hasSearchColumn = true;

                                    continue;
                                }
                                $sub->orWhere($column, 'ILIKE', $like);
                            }

                            $sub->orWhereRaw("TRIM(CONCAT_WS(' ', COALESCE(first_name, ''), COALESCE(last_name, ''))) ILIKE ?", [$like]);

                            $sub->orWhereHas('city', function (Builder $cityQuery) use ($like) {
                                $cityQuery->where('name', 'ILIKE', $like);
                            });
                        });
                    }
                });
            }
        }

        // Apply Membership Status Filter
        if ($membershipStatus) {
            $dbValue = match ($membershipStatus) {
                'only_unity_peer' => 'Only Unity Peer',
                'circle_peer' => 'Circle Peer',
                'multi_circle_peer' => 'Multi Circle Peer',
                'free_peer' => 'free_peer',
                'free_trial_peer' => 'free_trial_peer',
                default => $membershipStatus,
            };
            $query->where('membership_status', $dbValue);
        }

        // Sorting (default to count desc, then alphabetically by name asc)
        $sort = $request->input('sort', 'introduced_members_count');
        $direction = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'display_name') {
            $query->orderBy('display_name', $direction);
        } else {
            $query->orderBy('introduced_members_count', $direction)
                ->orderBy('display_name', 'asc');
        }

        $introducers = $query->paginate($perPage)->withQueryString();

        $membershipStatuses = ['circle_peer', 'multi_circle_peer', 'only_unity_peer', 'free_peer', 'free_trial_peer'];
        $membershipStatusLabels = $this->membershipFilterOptions();

        $filters = [
            'search' => $search,
            'membership_status' => $membershipStatus,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'per_page' => $perPage,
            'sort' => $sort,
            'dir' => $direction,
        ];

        return view('admin.member-introducers.index', [
            'topIntroducers' => $topIntroducers,
            'introducers' => $introducers,
            'canEditUsers' => $canEditUsers,
            'membershipStatuses' => $membershipStatuses,
            'membershipStatusLabels' => $membershipStatusLabels,
            'filters' => $filters,
        ]);
    }

    /**
     * Apply active scoping to restrict query by role.
     */
    private function applyScopes(Builder $query, $adminUser): void
    {
        AdminCircleScope::applyToUsersQuery($query, $adminUser);

        $industryScope = app(IndustryScopeService::class);
        if ($industryScope->isIndustryDirector($adminUser)) {
            $industryScope->applyPeersScope($query, $adminUser->id);
        }
    }

    /**
     * Get membership options for filter.
     */
    private function membershipFilterOptions(): array
    {
        return [
            'circle_peer' => 'Circle Peer',
            'multi_circle_peer' => 'Multi Circle Peer',
            'only_unity_peer' => 'Global Peer',
            'free_peer' => 'Free Peer',
            'free_trial_peer' => 'Free Trial Peer',
        ];
    }
}
