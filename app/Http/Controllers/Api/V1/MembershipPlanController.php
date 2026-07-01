<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;

class MembershipPlanController extends Controller
{
    public function __construct(private readonly MembershipService $membershipService) {}

    public function index(): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = $plans->map(function (MembershipPlan $plan): array {
            $amounts = $this->membershipService->calculateAmounts($plan);

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'gst_percent' => (float) $plan->gst_percent,
                'gst_amount' => $amounts['gst_amount'],
                'total_amount' => $amounts['total_amount'],
                'duration_days' => (int) $plan->duration_days,
                'duration_months' => $plan->duration_months ? (int) $plan->duration_months : null,
                'is_active' => (bool) $plan->is_active,
                'is_free' => (bool) $plan->is_free,
                'sort_order' => (int) $plan->sort_order,
                'coins' => (int) ($plan->coins ?? 0),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
