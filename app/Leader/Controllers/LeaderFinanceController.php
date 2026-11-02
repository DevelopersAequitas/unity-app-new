<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Requests\LeaderRecordOfflinePaymentRequest;
use App\Leader\Requests\LeaderUpdateCommissionRatesRequest;
use App\Leader\Services\LeaderFinanceService;
use App\Services\Finance\LeaderBonusEngineService;
use App\Leader\Services\LeaderPermissionService;
use App\Models\AdminUser;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderFinanceController extends Controller
{
    public function __construct(
        private readonly LeaderFinanceService $financeService,
    ) {}

    /**
     * Get financial metrics and collection summaries (including commission structure).
     */
    public function metrics(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->financeService->getMetrics($circleId, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Finance metrics retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Get transactions and dues ledger list.
     */
    public function transactions(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->financeService->getTransactions($circleId, $status, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update commission rates per role (Super Admin only).
     */
    public function updateCommissionRates(LeaderUpdateCommissionRatesRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $permissionService = app(LeaderPermissionService::class);
        $roleInfo = $user ? $permissionService->resolveUserRole($user) : ['role' => 'user'];
        $role = $roleInfo['role'];

        $isSuperAdmin = false;
        if ($role === 'superAdmin') {
            $isSuperAdmin = true;
        } elseif ($user instanceof AdminUser) {
            $isSuperAdmin = AdminAccess::isSuperAdmin($user) || AdminAccess::isGlobalAdmin($user) || in_array($user->role, ['super_admin', 'superAdmin']);
        } elseif ($user) {
            $admin = AdminUser::query()->where('id', $user->id)->orWhere('email', $user->email)->first();
            if ($admin && AdminAccess::isSuperAdmin($admin)) {
                $isSuperAdmin = true;
            } elseif ($user instanceof User && ($user->hasRole('super_admin') || $user->hasRole('superAdmin') || ! empty($user->is_super_admin))) {
                $isSuperAdmin = true;
            }
        }

        if (! $isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Super Admin has permission to modify commission structures.',
                'error_code' => 'FORBIDDEN_ROLE',
            ], 403);
        }

        $data = $this->financeService->updateCommissionRates(
            (array) $request->validated('commission_rates'),
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Commission rates updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Record manual / offline payment.
     */
    public function recordOfflinePayment(LeaderRecordOfflinePaymentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->financeService->recordOfflinePayment($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully.',
            'data' => $data,
        ], 201);
    }

    /**
     * Get milestone & performance bonus tracking for the logged-in leader.
     */
    public function milestones(Request $request): JsonResponse
    {
        /** @var User|AdminUser|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $permissionService = app(LeaderPermissionService::class);
        $roleInfo = $permissionService->resolveUserRole($user);
        $role = $roleInfo['role'] ?? 'circleFounder';

        $roleCode = match ($role) {
            'circleFounder' => 'CF',
            'circleDirector' => 'CD',
            'circleChair', 'chair' => 'CHAIR',
            'districtExecDirector' => 'DED',
            'industryDirector' => 'ID',
            'countryDirector' => 'CDIR',
            default => 'CF',
        };

        $bonusService = app(LeaderBonusEngineService::class);

        // If Super Admin, return the full catalog across all roles
        if ($role === 'superAdmin' || $user instanceof AdminUser) {
            return response()->json([
                'success' => true,
                'is_applicable' => false,
                'role' => 'superAdmin',
                'role_code' => 'SUPER',
                'message' => 'Super Admin manages milestone policy and does not earn personal bonuses.',
                'catalog' => $bonusService->listRules(),
            ]);
        }

        $summary = $bonusService->getLeaderPersonalBonusSummary((string) $user->id);
        $catalog = $bonusService->getRoleBonusCatalog($roleCode);
        $rules = $catalog['bonus_rules'] ?? [];
        $activeRulesCount = (int) ($catalog['active_rules_count'] ?? count($rules));

        // If Super Admin disabled all milestone rules for this role, mark not applicable
        $isApplicable = $activeRulesCount > 0;

        return response()->json([
            'success' => true,
            'is_applicable' => $isApplicable,
            'role' => $role,
            'role_code' => $roleCode,
            'role_label' => $roleInfo['custom_role_label'] ?? $permissionService->getRoleLabel($role),
            'milestones' => $rules,
            'catalog' => $catalog,
            'awards' => $summary['awards'] ?? [],
            'wallet' => $summary['wallet'] ?? null,
            'total_earned_bonuses' => $summary['total_earned_bonuses'] ?? '₹0',
        ]);
    }
}
