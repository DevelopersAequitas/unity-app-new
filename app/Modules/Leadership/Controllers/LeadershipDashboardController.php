<?php

declare(strict_types=1);

namespace App\Modules\Leadership\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Leader\Services\LeaderAuthService;
use App\Leader\Services\LeaderDashboardService;
use App\Models\User;
use App\Shared\Authorization\EffectiveAccessResolverService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadershipDashboardController extends BaseApiController
{
    public function __construct(
        private readonly LeaderAuthService $leaderAuthService,
        private readonly LeaderDashboardService $dashboardService,
        private readonly EffectiveAccessResolverService $accessResolver,
        private readonly OrganizationalScopeService $scopeService,
    ) {}

    /**
     * API 42: Current Authenticated Leader Context, Roles, Scope, Screens & Capabilities.
     * GET /api/v1/leadership/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $effective = $this->accessResolver->resolveEffectiveAccess($user);
        $profileData = $this->leaderAuthService->getProfile($user);

        return $this->success([
            'user' => array_merge($profileData['user'] ?? [], [
                'membership_status' => (string) ($user->membership_status ?? 'free_peer'),
                'primary_role_key' => $effective['primary_role_key'],
                'effective_role_keys' => $effective['effective_role_keys'],
                'effective_scope_type' => $effective['effective_scope_type'],
                'is_global' => $effective['is_global'],
            ]),
            'organizational_scope' => $effective['organizational_scope'],
            'permissions' => $effective['permissions'],
            'screens' => $effective['screens'],
            'resource_policies' => $effective['resource_policies'],
            'field_access_rules' => $effective['field_access_rules'],
            'allowed_reports' => $effective['allowed_reports'],
        ], 'Leadership context and capabilities resolved successfully.');
    }

    /**
     * API 43: Scoped Leadership Dashboard Summary & KPIs.
     * GET /api/v1/leadership/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        if ($circleId && Str::isUuid($circleId) && ! $this->scopeService->canAccessCircle($user, $circleId)) {
            return $this->error('You do not have organizational scope access to the requested circle.', 403);
        }

        $metrics = $this->dashboardService->getMetrics($circleId, $districtId, $user);
        $topImpacters = $this->dashboardService->getTopImpacters($circleId, $districtId, $user);
        $scope = $this->scopeService->resolveForActor($user, 'circles');

        return $this->success([
            'scope_summary' => [
                'primary_role_key' => $scope['primary_role_key'],
                'effective_scope_type' => $scope['effective_scope_type'],
                'is_global' => $scope['is_global'],
                'scoped_circle_ids' => $scope['circle_ids'],
                'scoped_district_ids' => $scope['district_ids'],
                'scoped_industry_ids' => $scope['industry_ids'],
            ],
            'metrics' => $metrics,
            'top_impacters' => $topImpacters,
        ], 'Leadership dashboard retrieved successfully.');
    }
}
