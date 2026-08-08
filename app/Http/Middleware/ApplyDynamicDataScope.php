<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\DataScopeResult;
use App\Services\Admin\PermissionService;
use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyDynamicDataScope
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $scope = $this->permissionService->dataScope($admin);

        // Set request attributes that existing controllers already read
        $request->attributes->set('data_scope', $scope);

        if ($scope->isGlobal) {
            $request->attributes->set('allowed_circle_ids', []);
            $request->attributes->set('is_circle_scoped', false);
            $request->attributes->set('is_ded_scoped', false);

            return $next($request);
        }

        $request->attributes->set('allowed_circle_ids', $scope->circleIds);

        match ($scope->scopeType) {
            'circle' => $this->applyCircleScope($request, $admin, $scope),
            'district' => $this->applyDistrictScope($request, $admin, $scope),
            'industry' => $this->applyIndustryScope($request, $scope),
            default => $this->applyDefaultScope($request),
        };

        return $next($request);
    }

    private function applyCircleScope(Request $request, $admin, DataScopeResult $scope): void
    {
        $request->attributes->set('is_circle_scoped', true);
        $request->attributes->set('is_ded_scoped', false);
        $request->attributes->set('primary_circle_role_label', AdminAccess::primaryCircleRoleLabel($admin));
    }

    private function applyDistrictScope(Request $request, $admin, DataScopeResult $scope): void
    {
        $request->attributes->set('is_circle_scoped', false);
        $request->attributes->set('is_ded_scoped', true);
        $request->attributes->set('ded_district_id', $scope->districtId);
        $request->attributes->set('ded_state_id', $scope->stateId);

        // Resolve district/state names for display
        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $request->attributes->set('ded_state_name', $dedLocation['state_name'] ?? null);
        $request->attributes->set('ded_district_name', $dedLocation['district_name'] ?? null);
    }

    private function applyIndustryScope(Request $request, DataScopeResult $scope): void
    {
        $request->attributes->set('is_circle_scoped', false);
        $request->attributes->set('is_industry_scoped', true);
        $request->attributes->set('is_ded_scoped', false);
    }

    private function applyDefaultScope(Request $request): void
    {
        $request->attributes->set('is_circle_scoped', false);
        $request->attributes->set('is_ded_scoped', false);
    }
}
