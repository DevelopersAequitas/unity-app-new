<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\ZohoCircleAddon;
use App\Support\Zoho\ZohoBillingClient;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CircleSubscriptionController extends Controller
{
    public function __construct(
        private readonly ZohoBillingClient $zohoBillingClient,
        private readonly ZohoBillingService $zohoBillingService,
    ) {
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'interval_type' => ['required', 'in:monthly,quarterly,half_yearly,yearly'],
        ]);

        $circle = Circle::query()->findOrFail($validated['circle_id']);

        $addon = ZohoCircleAddon::query()
            ->where('circle_id', $circle->id)
            ->where('interval_type', $validated['interval_type'])
            ->first();

        if (! $addon) {
            return response()->json([
                'success' => false,
                'message' => 'Addon is not available for the selected interval. Please ask admin to sync circle pricing first.',
                'data' => [],
            ], 422);
        }

        try {
            $customerId = $this->zohoBillingService->ensureCustomerForUser($request->user());

            $response = $this->zohoBillingClient->request('POST', '/hostedpages/newsubscription', [
                'customer_id' => $customerId,
                'plan' => [
                    'plan_code' => (string) env('ZOHO_CIRCLE_BASE_PLAN_CODE'),
                ],
                'addons' => [
                    ['addon_code' => $addon->zoho_addon_code],
                ],
            ]);

            $checkoutUrl = (string) data_get($response, 'hostedpage.url', '');

            if ($checkoutUrl === '') {
                Log::error('checkout create failed', [
                    'circle_id' => $circle->id,
                    'interval_type' => $validated['interval_type'],
                    'response' => $response,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create checkout right now.',
                    'data' => [],
                ], 422);
            }

            $addon->checkout_url = $checkoutUrl;
            $addon->save();

            Log::info('checkout created', [
                'user_id' => $request->user()?->id,
                'circle_id' => $circle->id,
                'interval_type' => $validated['interval_type'],
                'addon_code' => $addon->zoho_addon_code,
                'checkout_url' => $checkoutUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Checkout created successfully.',
                'data' => [
                    'circle_id' => $circle->id,
                    'interval_type' => $validated['interval_type'],
                    'addon_code' => $addon->zoho_addon_code,
                    'checkout_url' => $checkoutUrl,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('checkout create failed', [
                'user_id' => $request->user()?->id,
                'circle_id' => $circle->id,
                'interval_type' => $validated['interval_type'],
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create checkout right now.',
                'data' => [],
            ], 502);
        }
    }

    public function plans(Circle $circle): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Circle plans fetched successfully.',
            'data' => [
                'circle_id' => $circle->id,
                'circle_name' => $circle->name,
                'monthly' => $circle->price_monthly,
                'quarterly' => $circle->price_quarterly,
                'half_yearly' => $circle->price_half_yearly,
                'yearly' => $circle->price_yearly,
            ],
        ]);
    }
}
