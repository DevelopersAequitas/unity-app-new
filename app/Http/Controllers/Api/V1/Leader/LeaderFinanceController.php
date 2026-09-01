<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderRecordOfflinePaymentRequest;
use App\Http\Requests\Leader\LeaderUpdateCommissionRatesRequest;
use App\Models\AdminUser;
use App\Models\User;
use App\Services\Leader\LeaderFinanceService;
use App\Services\Leader\LeaderPermissionService;
use App\Support\AdminAccess;
use App\Models\User;
use App\Services\Leader\LeaderFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderFinanceController extends Controller
{
    public function __construct(
        private readonly LeaderFinanceService $financeService,
    ) {}

    /**
     * Get financial metrics and collection summaries (including commission structure).
     * Get financial metrics and collection summaries (including 10% DED commission).
     */
    public function metrics(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->financeService->getMetrics($circleId, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Finance metrics retrieved successfully',
            'message' => 'Finance metrics and trend datasets fetched successfully.',
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
        } elseif ($user) {
            $admin = AdminUser::query()->where('id', $user->id)->orWhere('email', $user->email)->first();
            if ($admin && AdminAccess::isSuperAdmin($admin)) {
                $isSuperAdmin = true;
            } elseif ($user->hasRole('super_admin') || $user->hasRole('superAdmin') || ! empty($user->is_super_admin)) {
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
     * Update commission rates per role (Super Admin).
     */
    public function updateCommissionRates(LeaderUpdateCommissionRatesRequest $request): JsonResponse
    {
        $this->financeService->updateCommissionRates((array) $request->validated('commission_rates'));

        return response()->json([
            'success' => true,
            'message' => 'Commission rates updated successfully.',
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
}
