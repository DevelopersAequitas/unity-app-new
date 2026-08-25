<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderRecordOfflinePaymentRequest;
use App\Http\Requests\Leader\LeaderUpdateCommissionRatesRequest;
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
     * Get financial metrics and collection summaries (including 10% DED commission).
     */
    public function metrics(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->financeService->getMetrics($circleId, $districtId, $request->user());

        return response()->json([
            'success' => true,
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
