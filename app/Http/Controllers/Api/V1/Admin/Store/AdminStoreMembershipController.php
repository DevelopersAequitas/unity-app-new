<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\MembershipLedger;
use App\Models\Store\StoreMembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreMembershipController extends BaseApiController
{
    public function plans(): JsonResponse
    {
        $plans = StoreMembershipPlan::orderBy('sort_order', 'asc')->get();

        return $this->success($plans, 'Admin membership plans retrieved');
    }

    public function storePlan(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'duration_months' => 'required|integer|min:1',
            'price_coins' => 'required|integer|min:1',
            'active' => 'nullable|boolean',
            'features' => 'nullable|array',
        ]);

        $plan = StoreMembershipPlan::create($request->all());

        return $this->success($plan, 'Membership plan created', 201);
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = StoreMembershipPlan::findOrFail($id);
        $plan->update($request->all());

        return $this->success($plan, 'Membership plan updated');
    }

    public function ledger(string $userId): JsonResponse
    {
        $records = MembershipLedger::with('plan')->where('user_id', $userId)->orderBy('created_at', 'desc')->get();

        return $this->success($records, 'User membership ledger retrieved');
    }
}
