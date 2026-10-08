<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreMembershipQuoteRequest;
use App\Http\Requests\Store\StoreMembershipRenewRequest;
use App\Services\Store\StoreMembershipService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends BaseApiController
{
    protected StoreMembershipService $membershipService;

    public function __construct(StoreMembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->membershipService->getMembershipStatus($user);

        return $this->success($status, 'Membership status retrieved');
    }

    public function plans(): JsonResponse
    {
        $plans = $this->membershipService->getActivePlans();

        return $this->success($plans, 'Active membership plans retrieved');
    }

    public function quote(StoreMembershipQuoteRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            $planId = $data['plan_id'] ?? null;
            $quote = $this->membershipService->quoteRenewal($user, $planId, $data);

            return $this->success($quote, 'Membership renewal quote calculated');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function renew(StoreMembershipRenewRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            $planId = $data['plan_id'] ?? null;
            $idempotencyKey = $request->header('Idempotency-Key');

            $result = $this->membershipService->renewMembership($user, $planId, $idempotencyKey, $data);

            return $this->success($result, 'Membership renewed successfully using coins');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
