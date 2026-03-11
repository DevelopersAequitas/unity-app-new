<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoPlansController extends Controller
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function index()
    {
        try {
            $cacheKey = 'zoho_active_plans';
            $allowedPlanCodes = ['012', '013', '014'];

            if (Cache::has($cacheKey)) {
                Log::info('Zoho plans cache hit', [
                    'cache_key' => $cacheKey,
                ]);
            }

            $plans = Cache::remember($cacheKey, 600, function () use ($cacheKey) {
                Log::info('Zoho plans cache miss, fetching from Zoho', [
                    'cache_key' => $cacheKey,
                ]);

                return $this->zohoBillingService->listActivePlans();
            });

            $plans = collect($plans)
                ->filter(function ($plan) use ($allowedPlanCodes) {
                    return in_array($plan['plan_code'] ?? null, $allowedPlanCodes, true);
                })
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'message' => 'Plans fetched successfully',
                'data' => $plans,
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
