<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use App\Support\AdminCircleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LifeImpactController extends Controller
{
    private const CATEGORIES = [
        'business_deals' => ['label' => 'Business Deals', 'points' => 5, 'aliases' => ['business_deal', 'business_deals', 'businessdeal']],
        'referrals' => ['label' => 'Referrals', 'points' => 1, 'aliases' => ['referral', 'referrals']],
        'testimonials' => ['label' => 'Testimonials', 'points' => 5, 'aliases' => ['testimonial', 'testimonials']],
        'mentorship' => ['label' => 'Mentorship', 'points' => 1, 'aliases' => ['mentorship', 'mentor', 'mentoring']],
        'joint_venture' => ['label' => 'Joint Venture', 'points' => 1, 'aliases' => ['joint_venture', 'joint_ventures', 'jointventure']],
        'knowledge_sharing' => ['label' => 'Knowledge Sharing', 'points' => 1, 'aliases' => ['knowledge_sharing', 'knowledge_share', 'knowledge']],
        'problem_solving' => ['label' => 'Problem Solving', 'points' => 1, 'aliases' => ['problem_solving', 'problem_solve', 'problem']],
        'vendor_connect' => ['label' => 'Vendor Connect', 'points' => 1, 'aliases' => ['vendor_connect', 'vendor_connection', 'vendor']],
        'funding_access' => ['label' => 'Funding Access', 'points' => 1, 'aliases' => ['funding_access', 'funding', 'fund_access']],
        'visibility_pr' => ['label' => 'Visibility & PR', 'points' => 1, 'aliases' => ['visibility_pr', 'visibility_and_pr', 'visibility', 'pr']],
        'emotional_support' => ['label' => 'Emotional Support', 'points' => 1, 'aliases' => ['emotional_support', 'emotional']],
        'execution_support' => ['label' => 'Execution Support', 'points' => 1, 'aliases' => ['execution_support', 'execution']],
    ];

    public function index(Request $request): View
    {
        $filters = $this->indexFilters($request);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $members = $this->membersQuery($filters)
            ->orderByDesc('total_life_impacted_sort')
            ->orderBy('users.display_name')
            ->paginate($perPage)
            ->appends($request->query());

        return view('admin.life-impact.index', [
            'members' => $members,
            'filters' => $filters,
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
            'categories' => self::CATEGORIES,
            'impactStats' => $this->impactStatsByUserId($members->pluck('id')->all()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->indexFilters($request);

        $query = $this->membersQuery($filters)
            ->orderByDesc('total_life_impacted_sort')
            ->orderBy('users.display_name');

        $filename = 'life_impact_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_merge(['Peer Name', 'Total Life Impacted'], array_column(self::CATEGORIES, 'label')));

            $query->chunk(500, function ($members) use ($handle): void {
                $stats = $this->impactStatsByUserId($members->pluck('id')->all());

                foreach ($members as $member) {
                    $rowStats = $stats[(string) $member->id] ?? [];
                    $row = [
                        $member->adminName(),
                        (int) ($member->life_impacted_count ?? 0),
                    ];

                    foreach (array_keys(self::CATEGORIES) as $key) {
                        $row[] = (int) ($rowStats[$key] ?? 0);
                    }

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function membersQuery(array $filters): Builder
    {
        $hasUsersName = Schema::hasColumn('users', 'name');
        $hasUsersCompany = Schema::hasColumn('users', 'company');
        $hasUsersBusinessName = Schema::hasColumn('users', 'business_name');

        $query = User::query()
            ->select([
                'users.id',
                'users.email',
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'users.company_name',
                'users.city',
                'users.life_impacted_count',
            ])
            ->addSelect(DB::raw('COALESCE(users.life_impacted_count, 0) as total_life_impacted_sort'))
            ->with(['circleMembers' => function ($circleMembersQuery) {
                $circleMembersQuery->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }]);

        AdminCircleScope::applyToUsersQuery($query, auth('admin')->user());

        $search = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        $circleId = (string) ($filters['circle_id'] ?? 'all');

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                $like = "%{$search}%";

                $searchQuery->where('users.display_name', 'ILIKE', $like)
                    ->orWhere('users.first_name', 'ILIKE', $like)
                    ->orWhere('users.last_name', 'ILIKE', $like)
                    ->orWhere('users.company_name', 'ILIKE', $like)
                    ->orWhere('users.city', 'ILIKE', $like);

                if ($hasUsersName) {
                    $searchQuery->orWhere('users.name', 'ILIKE', $like);
                }

                if ($hasUsersCompany) {
                    $searchQuery->orWhere('users.company', 'ILIKE', $like);
                }

                if ($hasUsersBusinessName) {
                    $searchQuery->orWhere('users.business_name', 'ILIKE', $like);
                }
            });
        }

        if ($circleId !== '' && $circleId !== 'all') {
            $query->whereHas('circleMembers', function ($circleMembersQuery) use ($circleId) {
                $circleMembersQuery->where('circle_id', $circleId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        return $query;
    }

    private function impactStatsByUserId(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $query = DB::table('life_impact_histories')
            ->whereIn('user_id', $memberIds)
            ->where(function ($q): void {
                $q->whereNull('counted_in_total')
                    ->orWhere('counted_in_total', true);
            })
            ->where(function ($q): void {
                $q->whereNull('activity_type')->orWhere('activity_type', '!=', 'admin_adjustment');
            })
            ->where(function ($q): void {
                $q->whereNull('impact_category')->orWhere('impact_category', '!=', 'admin_adjustment');
            })
            ->where(function ($q): void {
                $q->whereNull('action_key')->orWhere('action_key', '!=', 'admin_adjustment');
            })
            ->select(['user_id', 'activity_type', 'impact_category', 'action_key', 'action_label', 'title']);

        if (Schema::hasColumn('life_impact_histories', 'status')) {
            $query->where(function ($q): void {
                $q->whereNull('status')->orWhere('status', 'approved');
            });
        }

        return $query->get()
            ->reduce(function (array $stats, $history): array {
                $category = $this->resolveCategoryKey($history);

                if ($category !== null) {
                    $userId = (string) $history->user_id;
                    $stats[$userId][$category] = (int) ($stats[$userId][$category] ?? 0) + self::CATEGORIES[$category]['points'];
                }

                return $stats;
            }, []);
    }

    private function resolveCategoryKey(object $history): ?string
    {
        $tokens = collect([
            $history->action_key ?? null,
            $history->impact_category ?? null,
            $history->activity_type ?? null,
            $history->action_label ?? null,
            $history->title ?? null,
        ])
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => $this->normalizeCategoryToken((string) $value))
            ->filter()
            ->values();

        foreach (self::CATEGORIES as $key => $category) {
            foreach ($category['aliases'] as $alias) {
                if ($tokens->contains($alias)) {
                    return $key;
                }
            }
        }

        foreach ($tokens as $token) {
            foreach (self::CATEGORIES as $key => $category) {
                foreach ($category['aliases'] as $alias) {
                    if ($alias !== '' && str_contains($token, $alias)) {
                        return $key;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeCategoryToken(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
    }

    private function indexFilters(Request $request): array
    {
        $perPage = $request->integer('per_page') ?: 20;
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        return [
            'q' => trim((string) $request->query('q', $request->query('search', ''))),
            'search' => trim((string) $request->query('q', $request->query('search', ''))),
            'circle_id' => (string) $request->query('circle_id', 'all'),
            'per_page' => $perPage,
        ];
    }
}
