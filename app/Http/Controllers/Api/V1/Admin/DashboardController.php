<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\Impact;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Admin\AdminScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends BaseApiController
{
    public function __construct(
        private readonly AdminScopeService $scope,
        private readonly AdminDashboardMetricsService $metricsService,
    ) {}

    public function metrics(Request $request): JsonResponse
    {
        return $this->success($this->metricsService->getMetrics($request->user()));
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->metrics($request);
    }

    public function revenue(Request $request): JsonResponse
    {
        $user = $request->user();
        $amountColumn = $this->resolvePaymentAmountColumn();
        $categoryColumn = $this->resolvePaymentCategoryColumn();
        $query = Payment::query()->whereIn('status', $this->resolvePaidStatuses());

        if (! $this->scope->isGlobal($user)) {
            $query->whereIn('circle_id', $this->scope->visibleCircleIds($user));
        }

        if (! $categoryColumn) {
            $total = (float) $query->sum($amountColumn);

            return $this->success([
                'membership_revenue' => null,
                'circle_fee_revenue' => null,
                'event_revenue' => null,
                'sponsor_revenue' => null,
                'total_revenue' => $total,
            ]);
        }

        $rows = $query->selectRaw("COALESCE({$categoryColumn}, 'other') as source_key, SUM({$amountColumn}) as total")->groupBy($categoryColumn)->get();
        $mapped = $rows->pluck('total', 'source_key');

        return $this->success([
            'membership_revenue' => (float) ($mapped['membership'] ?? 0),
            'circle_fee_revenue' => (float) ($mapped['circle_fee'] ?? 0),
            'event_revenue' => (float) ($mapped['event'] ?? 0),
            'sponsor_revenue' => (float) ($mapped['sponsor'] ?? 0),
            'total_revenue' => (float) $rows->sum('total'),
        ]);
    }

    private function resolvePaymentAmountColumn(): string
    {
        foreach (['total_amount', 'amount', 'base_amount'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                return $column;
            }
        }

        return 'total_amount';
    }

    private function resolvePaymentCategoryColumn(): ?string
    {
        foreach (['source', 'type', 'payment_type', 'category', 'transaction_type', 'purpose'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function resolvePaidStatuses(): array
    {
        $distinct = DB::table('payments')->select('status')->whereNotNull('status')->distinct()->pluck('status')->map(fn ($s) => strtolower((string) $s))->all();
        $statuses = array_values(array_filter(['success', 'paid', 'completed'], fn ($candidate) => in_array($candidate, $distinct, true)));

        return $statuses !== [] ? $statuses : ['success'];
    }

    public function lifeImpact(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Impact::query()->where('status', 'approved');
        if (! $this->scope->isGlobal($user)) {
            $query->whereIn('user_id', User::query()->select('users.id')->tap(fn ($q) => $this->scope->applyUserScope($q, $user)));
        }

        $top = (clone $query)
            ->selectRaw('user_id, SUM(COALESCE(impact_score,impact_value,1)) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('user:id,first_name,last_name,display_name')
            ->get();

        return $this->success([
            'total_lives_impacted' => (int) $query->sum(DB::raw('COALESCE(impact_score,impact_value,1)')),
            'this_month_lives_impacted' => (int) (clone $query)->whereBetween('approved_at', [now()->startOfMonth(), now()->endOfMonth()])->sum(DB::raw('COALESCE(impact_score,impact_value,1)')),
            'top_contributors' => $top,
        ]);
    }

    public function membersGrowth(Request $request): JsonResponse
    {
        $query = User::query()->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as total")->groupBy('month')->orderBy('month');
        $this->scope->applyUserScope($query, $request->user());

        return $this->success($query->get());
    }

    public function circlesOverview(Request $request): JsonResponse
    {
        $query = Circle::query()->withCount(['members' => fn ($q) => $q->where('circle_members.status', 'approved')->whereNull('circle_members.deleted_at')]);
        $this->scope->applyCircleScope($query, $request->user());

        return $this->success($query->latest('updated_at')->limit(20)->get());
    }

    public function pendingCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $circleIds = $this->scope->visibleCircleIds($user);

        return $this->success([
            'pending_impacts' => Impact::query()->where('status', 'pending')->count(),
            'pending_coin_claims' => CoinClaimRequest::query()->where('status', 'pending')->count(),
            'pending_circle_join_requests' => CircleJoinRequest::query()->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee'])->when(! $this->scope->isGlobal($user), fn ($q) => $q->whereIn('circle_id', $circleIds))->count(),
            'pending_approvals_count' => Impact::query()->where('status', 'pending')->count() + CoinClaimRequest::query()->where('status', 'pending')->count(),
        ]);
    }
}
