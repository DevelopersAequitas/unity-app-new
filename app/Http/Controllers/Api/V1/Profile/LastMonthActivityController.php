<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Profile\LastMonthActivityRequest;
use App\Services\LastMonthActivityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LastMonthActivityController extends BaseApiController
{
    /**
     * Retrieve consolidated last month activity data.
     */
    public function index(LastMonthActivityRequest $request, LastMonthActivityService $service): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'data' => null,
                ], 401);
            }

            // Default to previous calendar month
            $prevMonthDate = Carbon::now()->subMonthNoOverflow();
            $defaultMonth = $prevMonthDate->month;
            $defaultYear = $prevMonthDate->year;

            $monthInput = $request->query('month');
            $yearInput = $request->query('year');

            $month = $monthInput !== null ? $request->parseMonth($monthInput) : $defaultMonth;
            $year = $yearInput !== null ? (int) $yearInput : $defaultYear;

            // In case request parsing fallback is needed (validation handles correctness)
            if ($month === null) {
                $month = $defaultMonth;
            }

            $data = $service->getActivityData($user, $month, $year);

            return $this->success($data, 'Last month activity data retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('LastMonthActivity retrieval failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while compiling your last month activity data. Please try again later.',
                'data' => null,
            ], 500);
        }
    }
}
