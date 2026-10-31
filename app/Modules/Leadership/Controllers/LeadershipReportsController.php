<?php

declare(strict_types=1);

namespace App\Modules\Leadership\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\User;
use App\Shared\Authorization\EffectiveAccessResolverService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadershipReportsController extends BaseApiController
{
    public function __construct(
        private readonly EffectiveAccessResolverService $accessResolver,
        private readonly OrganizationalScopeService $scopeService,
    ) {}

    /**
     * API 50a: List Allowed Reports for Authenticated Leader.
     * GET /api/v1/leadership/reports
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $effective = $this->accessResolver->resolveEffectiveAccess($user);

        return $this->success([
            'primary_role_key' => $effective['primary_role_key'],
            'effective_scope_type' => $effective['effective_scope_type'],
            'total' => count($effective['allowed_reports']),
            'reports' => $effective['allowed_reports'],
        ], 'Allowed leadership reports fetched successfully.');
    }

    /**
     * API 50b: Execute a Dynamic Scoped Report by reportKey.
     * GET /api/v1/leadership/reports/{reportKey}
     */
    public function execute(Request $request, string $reportKey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $effective = $this->accessResolver->resolveEffectiveAccess($user);

        $allowedReport = collect($effective['allowed_reports'])->firstWhere('report_key', $reportKey);
        if (! $allowedReport) {
            return $this->error("You do not have permission to view report '{$reportKey}'.", 403);
        }

        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        if ($circleId && Str::isUuid($circleId) && ! $this->scopeService->canAccessCircle($user, $circleId)) {
            return $this->error('You do not have organizational scope access to execute this report for the requested circle.', 403);
        }

        $scopedPeerIds = $this->scopeService->resolveScopedPeerIds($user, 'reports', $circleId);
        $scope = $this->scopeService->resolveForActor($user, 'reports');

        $rows = match ($reportKey) {
            'circle_summary_report', 'leadership_coverage_report' => $this->buildCircleSummaryReport($user, $circleId),
            'peer_growth_report', 'coin_distribution_report' => $this->buildPeerReport($scopedPeerIds),
            'activity_performance_report' => $this->buildActivityReport($scopedPeerIds),
            default => $this->buildCircleSummaryReport($user, $circleId),
        };

        return $this->success([
            'report' => $allowedReport,
            'scope_applied' => [
                'scope_type' => $scope['effective_scope_type'],
                'is_global' => $scope['is_global'],
                'circle_ids' => $circleId ? [$circleId] : $scope['circle_ids'],
            ],
            'total_rows' => count($rows),
            'rows' => $rows,
        ], "Report '{$reportKey}' executed successfully.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCircleSummaryReport(User $user, ?string $circleId): array
    {
        $q = Circle::query()->whereNull('deleted_at');
        $this->scopeService->applyCircleScope($q, $user, $circleId);

        return $q->orderBy('name')->get()->map(function (Circle $c): array {
            $peersCount = Schema::hasTable('circle_members')
                ? DB::table('circle_members')->where('circle_id', $c->id)->whereNull('deleted_at')->count()
                : 0;

            return [
                'circle_id' => (string) $c->id,
                'circle_name' => (string) $c->name,
                'status' => (string) $c->status,
                'peers_count' => $peersCount,
                'founder_assigned' => ! empty($c->founder_user_id),
            ];
        })->all();
    }

    /**
     * @param  array<int, string>|null  $scopedPeerIds
     * @return array<int, array<string, mixed>>
     */
    private function buildPeerReport(?array $scopedPeerIds): array
    {
        $q = User::query()->whereNull('deleted_at');
        if ($scopedPeerIds !== null) {
            if (empty($scopedPeerIds)) {
                return [];
            }
            $q->whereIn('id', $scopedPeerIds);
        }

        return $q->orderByDesc('coins_balance')->limit(50)->get()->map(fn (User $u): array => [
            'user_id' => (string) $u->id,
            'display_name' => (string) ($u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''))),
            'company_name' => (string) ($u->company_name ?? ''),
            'membership_status' => (string) ($u->membership_status ?? 'free_peer'),
            'coins_balance' => (int) ($u->coins_balance ?? 0),
            'influencer_stars' => (int) ($u->influencer_stars ?? 0),
        ])->all();
    }

    /**
     * @param  array<int, string>|null  $scopedPeerIds
     * @return array<int, array<string, mixed>>
     */
    private function buildActivityReport(?array $scopedPeerIds): array
    {
        $peers = $this->buildPeerReport($scopedPeerIds);

        return array_map(function (array $p): array {
            $uid = $p['user_id'];
            $refs = Schema::hasTable('referrals')
                ? DB::table('referrals')->where('from_user_id', $uid)->where('is_deleted', false)->count()
                : 0;
            $p2p = Schema::hasTable('p2p_meetings')
                ? DB::table('p2p_meetings')->where('initiator_user_id', $uid)->where('is_deleted', false)->count()
                : 0;
            $deals = Schema::hasTable('business_deals')
                ? DB::table('business_deals')->where('from_user_id', $uid)->where('is_deleted', false)->count()
                : 0;

            return array_merge($p, [
                'referrals_count' => $refs,
                'p2p_meetings_count' => $p2p,
                'business_deals_count' => $deals,
            ]);
        }, $peers);
    }
}

